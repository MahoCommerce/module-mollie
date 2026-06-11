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

        // Paid/pending/authorized/open all go to the success page — the webhook finalizes state.
        if ($molliePayment->isPaid()
            || $molliePayment->isAuthorized()
            || $molliePayment->isPending()
            || $molliePayment->isOpen()
        ) {
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
