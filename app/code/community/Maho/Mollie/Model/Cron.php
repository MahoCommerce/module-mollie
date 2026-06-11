<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
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
        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        $methodCodes = $helper->getMollieMethodCodes();
        if ($methodCodes === []) {
            return;
        }

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
        $orders->getSelect()->where('payment.method IN (?)', $methodCodes);

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

        $paymentId = (string) $payment->getAdditionalInformation('mollie_payment_id');
        if ($paymentId === '') {
            return;
        }

        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        $client = $helper->getApiClient((int) $order->getStoreId());
        $molliePayment = $client->payments->get($paymentId);

        $this->reconcile($order, $molliePayment, 'cron');
    }

    /**
     * Apply the Mollie payment status to the Maho order. Idempotent: safe to call
     * repeatedly with the same payment state.
     */
    public function reconcile(
        Mage_Sales_Model_Order $order,
        \Mollie\Api\Resources\Payment $molliePayment,
        string $source = 'webhook',
    ): void {
        $status = (string) $molliePayment->status;
        $incrementId = (string) $order->getIncrementId();

        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        $storeId = (int) $order->getStoreId();
        $debug = $helper->isDebugEnabled($storeId);

        if ($debug) {
            Mage::log(
                "Mollie {$source}: order #{$incrementId} payment id={$molliePayment->id} status={$status}",
                Mage::LOG_INFO,
                'mollie.log',
            );
        }

        if ($molliePayment->isPaid() || $molliePayment->isAuthorized()) {
            if (!$order->hasInvoices()) {
                $amount = (float) $molliePayment->amount->value;
                $orderPayment = $order->getPayment();
                if (!$orderPayment) {
                    return;
                }

                $orderPayment->setTransactionId((string) $molliePayment->id);
                $orderPayment->setCurrencyCode((string) $molliePayment->amount->currency);
                $orderPayment->setIsTransactionClosed(true);
                // Second arg true => auto-create an invoice on capture.
                $orderPayment->registerCaptureNotification($amount, true);
                $order->save();

                // registerCaptureNotification puts the order in STATE_PROCESSING with
                // the default processing status. Apply the merchant-configured status
                // (which may differ) while leaving the state as-is.
                /** @var Maho_Mollie_Helper_Data $helper */
                $helper = Mage::helper('maho_mollie');
                $methodCode = (string) $orderPayment->getMethod();
                $processingStatus = $helper->getProcessingStatus((int) $order->getStoreId(), $methodCode);
                if ($processingStatus !== '' && $processingStatus !== (string) $order->getStatus()) {
                    $order->setStatus($processingStatus);
                    $order->addStatusHistoryComment(
                        $helper->__('Order status set to "%s" per Mollie configuration.', $processingStatus),
                        $processingStatus,
                    )->setIsCustomerNotified(false);
                    $order->save();
                }

                if ($debug) {
                    Mage::log(
                        "Mollie {$source}: order #{$incrementId} captured {$amount} {$molliePayment->amount->currency}",
                        Mage::LOG_INFO,
                        'mollie.log',
                    );
                }
            }

            // A paid/authorized payment can still have refunds or chargebacks
            // arrive asynchronously — fall through to those checks below.
            if ($molliePayment->hasRefunds()) {
                $this->_processExternalRefunds($order, $molliePayment, $source);
            }
            if ($molliePayment->hasChargebacks()) {
                $this->_processChargebacks($order, $molliePayment, $source);
            }
            return;
        }

        if ($molliePayment->isCanceled() || $molliePayment->isExpired() || $molliePayment->isFailed()) {
            if ($order->isCanceled() || $order->getState() === Mage_Sales_Model_Order::STATE_CANCELED) {
                return; // already canceled
            }

            $order->cancel()->save();
            if ($debug) {
                Mage::log(
                    "Mollie {$source}: order #{$incrementId} canceled (status={$status})",
                    Mage::LOG_INFO,
                    'mollie.log',
                );
            }
            return;
        }

        if ($molliePayment->isOpen() || $molliePayment->isPending()) {
            // Still waiting for the customer — nothing to do.
            return;
        }

        if ($molliePayment->hasRefunds()) {
            $this->_processExternalRefunds($order, $molliePayment, $source);
        }

        if ($molliePayment->hasChargebacks()) {
            $this->_processChargebacks($order, $molliePayment, $source);
        }

        if ($molliePayment->hasRefunds() || $molliePayment->hasChargebacks()) {
            return;
        }

        Mage::log(
            "Mollie {$source}: unhandled status '{$status}' for order #{$incrementId}",
            Mage::LOG_WARNING,
            'mollie.log',
        );
    }

    /**
     * Register any Mollie refunds that were NOT initiated from this Maho instance.
     *
     * Refunds initiated from admin creditmemo save (Method_Standard::refund) are
     * tracked locally in payment additional_information under 'mollie_refund_ids';
     * those are skipped here because Maho already created the creditmemo.
     *
     * Refunds initiated externally (Mollie dashboard, direct API) have no local
     * record, so we create an offline creditmemo via registerRefundNotification.
     * Idempotency is provided by Maho's transaction bookkeeping: the refund id
     * is used as the transaction id, and _isTransactionExists short-circuits
     * duplicate notifications.
     */
    protected function _processExternalRefunds(
        Mage_Sales_Model_Order $order,
        \Mollie\Api\Resources\Payment $molliePayment,
        string $source,
    ): void {
        $incrementId = (string) $order->getIncrementId();
        $orderPayment = $order->getPayment();
        if (!$orderPayment) {
            return;
        }

        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        $debug = $helper->isDebugEnabled((int) $order->getStoreId());

        $knownIds = $this->_getKnownRefundIds($orderPayment);

        try {
            $refunds = $molliePayment->refunds();
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::log(
                "Mollie {$source}: failed to list refunds for order #{$incrementId}: {$e->getMessage()}",
                Mage::LOG_ERROR,
                'mollie.log',
            );
            return;
        }

        foreach ($refunds as $refund) {
            /** @var \Mollie\Api\Resources\Refund $refund */
            $refundId = (string) $refund->id;
            if ($refundId === '') {
                continue;
            }

            // Refund we initiated from Maho — creditmemo already exists.
            if (in_array($refundId, $knownIds, true)) {
                if ($debug) {
                    Mage::log(
                        "Mollie {$source}: refund {$refundId} for order #{$incrementId} already processed locally",
                        Mage::LOG_INFO,
                        'mollie.log',
                    );
                }
                continue;
            }

            // Failed/canceled refunds shouldn't produce a creditmemo.
            if (method_exists($refund, 'isFailed') && $refund->isFailed()) {
                continue;
            }
            if (method_exists($refund, 'isCanceled') && $refund->isCanceled()) {
                continue;
            }

            $amount = (float) ($refund->amount->value ?? 0);
            $currency = (string) ($refund->amount->currency ?? $order->getOrderCurrencyCode());

            if ($amount <= 0) {
                continue;
            }

            try {
                $orderPayment->setParentTransactionId($orderPayment->getLastTransId() ?: (string) $molliePayment->id);
                $orderPayment->setTransactionId($refundId);
                $orderPayment->setIsTransactionClosed(true);
                $orderPayment->setCurrencyCode($currency);
                $orderPayment->registerRefundNotification($amount);

                $order->addStatusHistoryComment(
                    Mage::helper('maho_mollie')->__(
                        'Mollie refund %s (%s %s) was initiated outside Maho — offline credit memo created.',
                        $refundId,
                        number_format($amount, 2, '.', ''),
                        $currency,
                    ),
                );
                $order->save();

                // Track it so subsequent webhook redeliveries recognise it.
                $knownIds[] = $refundId;
                $this->_persistKnownRefundIds($orderPayment, $knownIds);
                $orderPayment->save();

                if ($debug) {
                    Mage::log(
                        "Mollie {$source}: external refund {$refundId} registered for order #{$incrementId} "
                        . "amount={$amount} {$currency}",
                        Mage::LOG_INFO,
                        'mollie.log',
                    );
                }
            } catch (\Throwable $e) {
                Mage::logException($e);
                Mage::log(
                    "Mollie {$source}: failed to register refund {$refundId} for order #{$incrementId}: "
                    . $e->getMessage(),
                    Mage::LOG_ERROR,
                    'mollie.log',
                );
            }
        }
    }

    /**
     * Handle chargeback notifications. Safer default: only log + add an order
     * comment, do NOT auto-create a credit memo. Chargebacks can affect
     * accounting differently than voluntary refunds; leave resolution to admin.
     */
    protected function _processChargebacks(
        Mage_Sales_Model_Order $order,
        \Mollie\Api\Resources\Payment $molliePayment,
        string $source,
    ): void {
        $incrementId = (string) $order->getIncrementId();
        $amount = $molliePayment->getAmountChargedBack();
        $currency = (string) ($molliePayment->amount->currency ?? $order->getOrderCurrencyCode());

        $orderPayment = $order->getPayment();
        $commentKey = 'mollie_chargeback_notified_amount';
        $already = '';
        if ($orderPayment instanceof Mage_Sales_Model_Order_Payment) {
            $already = (string) ($orderPayment->getAdditionalInformation($commentKey) ?? '');
        }
        $current = number_format($amount, 2, '.', '');
        if ($already === $current) {
            return; // already notified for this total — idempotent
        }

        $message = Mage::helper('maho_mollie')->__(
            'Mollie chargeback detected for payment %s (total charged back: %s %s). '
            . 'Review and create an offline credit memo if needed.',
            (string) $molliePayment->id,
            $current,
            $currency,
        );
        $order->addStatusHistoryComment($message, false)
            ->setIsCustomerNotified(false);
        $order->save();

        if ($orderPayment instanceof Mage_Sales_Model_Order_Payment) {
            $orderPayment->setAdditionalInformation($commentKey, $current);
            $orderPayment->save();
        }

        Mage::log(
            "Mollie {$source}: chargeback on order #{$incrementId} amount={$current} {$currency}",
            Mage::LOG_WARNING,
            'mollie.log',
        );
    }

    /**
     * @return list<string>
     */
    protected function _getKnownRefundIds(Mage_Sales_Model_Order_Payment $orderPayment): array
    {
        $stored = $orderPayment->getAdditionalInformation('mollie_refund_ids');
        if (is_array($stored)) {
            return array_values(array_filter(
                $stored,
                static fn($v): bool => is_string($v) && $v !== '',
            ));
        }
        if (is_string($stored) && $stored !== '') {
            try {
                /** @var Mage_Core_Helper_Data $coreHelper */
                $coreHelper = Mage::helper('core');
                $decoded = $coreHelper->jsonDecode($stored);
                if (is_array($decoded)) {
                    return array_values(array_filter(
                        $decoded,
                        static fn($v): bool => is_string($v) && $v !== '',
                    ));
                }
            } catch (\Throwable) {
                // fall through to empty list
            }
        }
        return [];
    }

    /**
     * @param list<string> $ids
     */
    protected function _persistKnownRefundIds(
        Mage_Sales_Model_Order_Payment $orderPayment,
        array $ids,
    ): void {
        /** @var Mage_Core_Helper_Data $coreHelper */
        $coreHelper = Mage::helper('core');
        $orderPayment->setAdditionalInformation(
            'mollie_refund_ids',
            $coreHelper->jsonEncode(array_values(array_unique($ids))),
        );
    }
}
