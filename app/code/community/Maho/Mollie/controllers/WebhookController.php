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
            $order = $this->_findOrderByMolliePaymentId($paymentId);
            if (!$order) {
                Mage::log(
                    "Mollie webhook: no order found for payment id={$paymentId}",
                    Mage::LOG_WARNING,
                    'mollie.log',
                );
                // Return 200 so Mollie doesn't keep retrying for an order we can't match.
                $this->getResponse()->setHttpResponseCode(200);
                return;
            }

            /** @var Maho_Mollie_Helper_Data $helper */
            $helper = Mage::helper('maho_mollie');
            $client = $helper->getApiClient((int) $order->getStoreId());
            $molliePayment = $client->payments->get($paymentId);

            /** @var Maho_Mollie_Model_Cron $reconciler */
            $reconciler = Mage::getModel('maho_mollie/cron');
            $reconciler->reconcile($order, $molliePayment, 'webhook');

            $this->getResponse()->setHttpResponseCode(200);
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::log(
                "Mollie webhook: error processing id={$paymentId}: {$e->getMessage()}",
                Mage::LOG_ERROR,
                'mollie.log',
            );
            $this->getResponse()->setHttpResponseCode(500);
        }
    }

    protected function _findOrderByMolliePaymentId(string $paymentId): ?Mage_Sales_Model_Order
    {
        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        $methodCodes = $helper->getMollieMethodCodes();
        if ($methodCodes === []) {
            return null;
        }

        $resource = Mage::getSingleton('core/resource');
        $paymentTable = $resource->getTableName('sales/order_payment');

        /** @var Mage_Sales_Model_Resource_Order_Collection $collection */
        $collection = Mage::getModel('sales/order')->getCollection();
        $collection->getSelect()->join(
            ['payment' => $paymentTable],
            'payment.parent_id = main_table.entity_id',
            [],
        );
        $collection->getSelect()->where('payment.method IN (?)', $methodCodes);
        $collection->getSelect()->where(
            'payment.additional_information LIKE ?',
            '%' . $paymentId . '%',
        );
        $collection->setPageSize(5);

        foreach ($collection as $candidate) {
            $payment = $candidate->getPayment();
            if ($payment && (string) $payment->getAdditionalInformation('mollie_payment_id') === $paymentId) {
                return $candidate;
            }
        }

        return null;
    }
}
