<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

class Maho_Mollie_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Create the Mollie Payment and redirect the customer to the Mollie checkout.
     */
    #[\Maho\Config\Route('/mollie/payment/redirect', name: 'mollie.payment.redirect')]
    public function redirectAction(): void
    {
        $session = Mage::getSingleton('checkout/session');
        $orderIncrementId = $session->getLastRealOrderId();

        if (!$orderIncrementId) {
            $this->_redirect('checkout/cart');
            return;
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $this->_redirect('checkout/cart');
            return;
        }

        $payment = $order->getPayment();
        if (!$payment) {
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            /** @var Maho_Mollie_Model_Method_Standard $method */
            $method = $payment->getMethodInstance();
            $result = $method->createPayment($order);

            // Methods that settle days later (bank transfer) mail the customer now:
            // they need the order details in hand to complete the payment. Instant
            // methods wait for capture instead, in Model_Cron::reconcile().
            if ($method->shouldSendOrderEmailOnPlacement()) {
                /** @var Maho_Mollie_Helper_Data $helper */
                $helper = Mage::helper('maho_mollie');
                $helper->sendOrderConfirmationEmail($order, 'placement');
            }

            $session->setMollieQuoteId($session->getQuoteId());
            $session->unsQuoteId();

            $this->getResponse()->setRedirect($result['redirectUrl']);
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('core/session')->addError(
                Mage::helper('maho_mollie')->__('Unable to initialize payment. Please try again.'),
            );
            $this->_restoreCart($order);
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Customer returns here after completing (or attempting) payment on Mollie.
     *
     * Mollie's return URL is a "best effort" redirect — it does NOT guarantee the
     * payment is already settled. The actual status update must come from the webhook.
     * Here we just move the customer to the correct confirmation/cart page based on
     * whatever status the Payment currently has.
     */
    #[\Maho\Config\Route('/mollie/payment/return', name: 'mollie.payment.return')]
    public function returnAction(): void
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getMollieQuoteId(true));

        $orderIncrementId = (string) $session->getLastRealOrderId();
        if ($orderIncrementId === '') {
            $this->_redirect('checkout/cart');
            return;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $this->_redirect('checkout/cart');
            return;
        }

        $payment = $order->getPayment();
        $paymentId = $payment ? (string) $payment->getAdditionalInformation('mollie_payment_id') : '';
        if ($paymentId === '') {
            // No Mollie id recorded — we can't verify; push customer back to cart.
            $this->_restoreCart($order);
            Mage::getSingleton('core/session')->addError(
                Mage::helper('maho_mollie')->__('We could not verify your Mollie payment. Please try again.'),
            );
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            /** @var Maho_Mollie_Helper_Data $helper */
            $helper = Mage::helper('maho_mollie');
            $client = $helper->getApiClient((int) $order->getStoreId());
            $molliePayment = $client->payments->get($paymentId);
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('core/session')->addError(
                Mage::helper('maho_mollie')->__('There was a problem verifying your payment. Please try again.'),
            );
            $this->_restoreCart($order);
            $this->_redirect('checkout/cart');
            return;
        }

        // Paid/pending/authorized/open all go to the success page.
        if ($molliePayment->isPaid()
            || $molliePayment->isAuthorized()
            || $molliePayment->isPending()
            || $molliePayment->isOpen()
        ) {
            // Mollie's webhook is the source of truth, but it is frequently
            // unreachable in sandbox/local-dev setups, which would leave a paid
            // order stuck in pending_payment until the cron eventually catches
            // it. Finalize synchronously here too; reconcile() is idempotent, so
            // a later webhook/cron pass is a no-op.
            if ($molliePayment->isPaid() || $molliePayment->isAuthorized()) {
                try {
                    /** @var Maho_Mollie_Model_Cron $reconciler */
                    $reconciler = Mage::getModel('maho_mollie/cron');
                    $reconciler->reconcile($order, $molliePayment, 'return');
                } catch (\Throwable $e) {
                    // Don't block the success page on reconciliation issues —
                    // the webhook/cron will retry.
                    Mage::logException($e);
                }
            }

            $quote = $session->getQuote();
            if ($quote->getId()) {
                $quote->setIsActive(0)->save();
            }
            $this->_redirect('checkout/onepage/success', ['_secure' => true]);
            return;
        }

        if ($molliePayment->isCanceled() || $molliePayment->isExpired() || $molliePayment->isFailed()) {
            $this->_restoreCart($order);
            Mage::getSingleton('core/session')->addError(
                Mage::helper('maho_mollie')->__('Your payment was not completed. Please try again.'),
            );
            $this->_redirect('checkout/cart');
            return;
        }

        // Unknown status — treat as pending, let the webhook sort it out.
        $this->_redirect('checkout/onepage/success', ['_secure' => true]);
    }

    /**
     * Customer aborted the payment on the Mollie hosted checkout page.
     *
     * Mollie redirects here (the `cancelUrl`) instead of the return URL when the
     * customer clicks "Cancel payment". In live mode the Mollie payment is often
     * still `open` or `pending` at this point — the webhook flips it to
     * `canceled` a little later — so we can't rely on the status like
     * returnAction() does. Unless Mollie already reports the payment as
     * paid/authorized, cancel the order and put the quote back in the cart.
     */
    #[\Maho\Config\Route('/mollie/payment/cancel', name: 'mollie.payment.cancel')]
    public function cancelAction(): void
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getMollieQuoteId(true));

        $orderIncrementId = (string) $session->getLastRealOrderId();
        if ($orderIncrementId === '') {
            $this->_redirect('checkout/cart');
            return;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $this->_redirect('checkout/cart');
            return;
        }

        // Guard against a cancel redirect that arrives after the payment was
        // actually settled: never cancel an order Mollie says is paid.
        $payment = $order->getPayment();
        $paymentId = $payment ? (string) $payment->getAdditionalInformation('mollie_payment_id') : '';
        if ($paymentId !== '') {
            try {
                /** @var Maho_Mollie_Helper_Data $helper */
                $helper = Mage::helper('maho_mollie');
                $client = $helper->getApiClient((int) $order->getStoreId());
                $molliePayment = $client->payments->get($paymentId);
                if ($molliePayment->isPaid() || $molliePayment->isAuthorized()) {
                    $this->_redirect('mollie/payment/return', ['_secure' => true]);
                    return;
                }
            } catch (\Throwable $e) {
                // Can't verify — the customer said they cancelled, so treat it as
                // cancelled. The webhook/cron will still reconcile if Mollie disagrees.
                Mage::logException($e);
            }
        }

        if ($order->canCancel()) {
            $this->_restoreCart($order);
        } else {
            // Order already cancelled (e.g. by the webhook) — just re-activate the quote.
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId('')->save();
                $session->replaceQuote($quote);
            }
        }

        Mage::getSingleton('core/session')->addError(
            Mage::helper('maho_mollie')->__('Your payment was cancelled. Your cart has been restored.'),
        );
        $this->_redirect('checkout/cart');
    }

    protected function _restoreCart(Mage_Sales_Model_Order $order): void
    {
        $order->cancel()->save();
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        if ($quote->getId()) {
            $quote->setIsActive(1)->setReservedOrderId('')->save();
            Mage::getSingleton('checkout/session')->replaceQuote($quote);
        }
    }
}
