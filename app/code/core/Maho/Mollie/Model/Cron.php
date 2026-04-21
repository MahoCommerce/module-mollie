<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_Cron
{
    /**
     * Check pending Mollie payments and reconcile their status.
     *
     * Runs every 5 minutes. Catches orders stuck in pending_payment (e.g. webhook
     * failed to arrive) and verifies them against the Mollie API.
     */
    public function checkPendingPayments(): void
    {
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('state', Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
            // TODO: switch to Mage::app()->getLocale()->formatDateForDb('-24 hours') once Maho 26.5+ is the minimum.
            ->addFieldToFilter('created_at', ['gteq' => date('Y-m-d H:i:s', strtotime('-24 hours'))])
            ->setPageSize(50);

        $orders->getSelect()->join(
            ['payment' => Mage::getSingleton('core/resource')->getTableName('sales/order_payment')],
            'payment.parent_id = main_table.entity_id',
            [],
        );
        $orders->getSelect()->where('payment.method = ?', 'mollie');

        foreach ($orders as $order) {
            try {
                $this->_checkOrder($order);
            } catch (\Throwable $e) {
                Mage::log(
                    "Mollie cron: error checking order #{$order->getIncrementId()}: {$e->getMessage()}",
                    Mage::LOG_ERROR,
                    'mollie.log',
                );
            }
        }
    }

    protected function _checkOrder(Mage_Sales_Model_Order $order): void
    {
        $payment = $order->getPayment();
        if (!$payment) {
            return;
        }

        $paymentId = $payment->getAdditionalInformation('mollie_payment_id');
        if (!$paymentId) {
            return;
        }

        // TODO: port from M2 Cron/PendingPaymentReminder.php — fetch the Mollie Payment,
        // branch on $molliePayment->status:
        //   - 'paid'      -> registerCaptureNotification
        //   - 'canceled'  -> $order->cancel()
        //   - 'expired'   -> $order->cancel()
        //   - 'failed'    -> $order->cancel()
    }
}
