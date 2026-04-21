<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

        // TODO: port from M2 Controller/Checkout/Process.php — fetch the Mollie Payment,
        // branch on status: 'paid' -> success page; 'open'/'pending' -> success page
        // (webhook will finalize); 'canceled'/'expired'/'failed' -> cart with error.

        $quote = $session->getQuote();
        if ($quote->getId()) {
            $quote->setIsActive(0)->save();
        }
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
