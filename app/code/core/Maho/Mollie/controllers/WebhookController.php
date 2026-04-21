<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_WebhookController extends Mage_Core_Controller_Front_Action
{
    /**
     * Handle webhook callbacks from Mollie.
     *
     * Mollie sends a single POST with an 'id' parameter (the Mollie Payment ID) whenever
     * a payment's status changes. We re-fetch the Payment via the API and reconcile.
     */
    public function indexAction(): void
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(405);
            return;
        }

        $paymentId = (string) $this->getRequest()->getPost('id', '');
        if ($paymentId === '') {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        try {
            // TODO: port from M2 Controller/Checkout/Webhook.php
            // 1. Find the local order by mollie_payment_id stored in payment additional_information.
            // 2. Fetch $molliePayment = $client->payments->get($paymentId) with the store-scoped client.
            // 3. Branch on $molliePayment->status:
            //      'paid'     -> registerCaptureNotification, create invoice
            //      'canceled' -> $order->cancel()
            //      'expired'  -> $order->cancel()
            //      'failed'   -> $order->cancel()
            //      'refunded' / 'charged_back' -> handled via refund webhook path
            // 4. Return 200 for any recognized status so Mollie stops retrying.

            Mage::log("Mollie webhook received for payment id={$paymentId} (not yet implemented)", Mage::LOG_INFO, 'mollie.log');
            $this->getResponse()->setHttpResponseCode(200);
        } catch (\Throwable $e) {
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
    }
}
