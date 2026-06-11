<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

class Maho_Mollie_Model_Method_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'mollie';

    protected $_formBlockType = 'maho_mollie/form';
    protected $_infoBlockType = 'maho_mollie/info';

    /**
     * If set, the method only becomes available when the quote currency matches.
     * Mollie rejects single-currency methods at the API with 422, so we guard
     * earlier in checkout. Subclasses declare their constraint (e.g. EUR, CHF).
     */
    protected ?string $_requiredCurrency = null;

    protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_isInitializeNeeded = true;
    protected $_canFetchTransactionInfo = true;

    #[\Override]
    public function isAvailable($quote = null): bool
    {
        if (!$this->_getMollieHelper()->hasCredentials($quote?->getStoreId())) {
            return false;
        }
        if ($this->_requiredCurrency !== null
            && $quote !== null
            && strtoupper((string) $quote->getQuoteCurrencyCode()) !== $this->_requiredCurrency
        ) {
            return false;
        }
        return parent::isAvailable($quote);
    }

    public function getOrderPlaceRedirectUrl(): string
    {
        return Mage::getUrl('mollie/payment/redirect', ['_secure' => true]);
    }

    /**
     * @param \Maho\DataObject $stateObject
     */
    #[\Override]
    public function initialize($paymentAction, $stateObject): self
    {
        $storeId = null;
        try {
            $info = $this->getInfoInstance();
            if ($info !== null) {
                $source = $info->getOrder() ?: $info->getQuote();
                if ($source !== null && $source->getStoreId() !== null) {
                    $storeId = (int) $source->getStoreId();
                }
            }
        } catch (\Throwable) {
            $storeId = null;
        }

        $statusCode = $this->_getMollieHelper()->getPendingStatus($storeId, $this->getCode());

        // Resolve the state the configured status is attached to. Falls back
        // to STATE_PENDING_PAYMENT if the status row is missing or unassigned.
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        /** @var Mage_Sales_Model_Order_Config $orderConfig */
        $orderConfig = Mage::getSingleton('sales/order_config');
        foreach ($orderConfig->getStatusStates($statusCode) as $statusState) {
            $resolvedState = (string) $statusState->getState();
            if ($resolvedState !== '') {
                $state = $resolvedState;
                break;
            }
        }

        $stateObject->setData('state', $state);
        $stateObject->setData('status', $statusCode);
        $stateObject->setData('is_notified', false);
        return $this;
    }

    /**
     * Create a Mollie Payment for the given order and return the checkout redirect URL.
     *
     * @return array{paymentId: string, redirectUrl: string}
     */
    public function createPayment(Mage_Sales_Model_Order $order): array
    {
        $helper = $this->_getMollieHelper();
        $orderPayment = $order->getPayment();
        $storeId = (int) $order->getStoreId();

        try {
            $client = $helper->getApiClient($storeId);

            $payload = [
                'amount' => [
                    'currency' => (string) $order->getOrderCurrencyCode(),
                    'value'    => $helper->formatAmount((float) $order->getGrandTotal()),
                ],
                'description' => 'Order #' . $order->getIncrementId(),
                'redirectUrl' => $helper->getReturnUrl($storeId),
                'webhookUrl'  => $helper->getWebhookUrl($storeId),
                'metadata'    => [
                    'order_id' => $order->getIncrementId(),
                ],
                'locale' => $helper->getLocale($storeId),
            ];

            // Optional: a specific Mollie method code chosen at checkout (iDEAL, etc.)
            // Priority: explicit `mollie_method_code` on payment, else per-subclass default.
            $selectedMethod = '';
            if ($orderPayment instanceof Mage_Sales_Model_Order_Payment) {
                $selectedMethod = (string) $orderPayment->getAdditionalInformation('mollie_method_code');
            }
            if ($selectedMethod === '') {
                $fallback = $this->getMollieMethodCode();
                if ($fallback !== null && $fallback !== '') {
                    $selectedMethod = $fallback;
                }
            }
            if ($selectedMethod !== '') {
                $payload['method'] = $selectedMethod;
            }

            // Subclasses can add method-specific fields (e.g. cardToken for
            // Components, issuer for iDEAL). Returned keys take precedence over
            // the generic payload above.
            $extra = $this->getExtraPaymentPayload($order);
            if ($extra !== []) {
                $payload = array_merge($payload, $extra);
            }

            $molliePayment = $client->payments->create($payload);

            $checkoutUrl = $molliePayment->getCheckoutUrl();
            if ($checkoutUrl === null || $checkoutUrl === '') {
                Mage::throwException($helper->__('Mollie did not return a checkout URL.'));
            }

            if ($orderPayment instanceof Mage_Sales_Model_Order_Payment) {
                $orderPayment->setAdditionalInformation('mollie_payment_id', (string) $molliePayment->id);
                $orderPayment->setAdditionalInformation('checkout_url', $checkoutUrl);
                $orderPayment->save();
            }

            return [
                'paymentId'   => (string) $molliePayment->id,
                'redirectUrl' => $checkoutUrl,
            ];
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::throwException($helper->__(
                'Unable to start Mollie payment for order #%s. Please try again.',
                $order->getIncrementId(),
            ));
        }
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function capture(\Maho\DataObject $payment, $amount): self
    {
        $paymentId = $payment->getAdditionalInformation('mollie_payment_id');
        if ($paymentId) {
            $payment->setTransactionId((string) $paymentId);
            $payment->setIsTransactionClosed(true);
        }
        return $this;
    }

    /**
     * Issue an online refund against the Mollie payment.
     *
     * Called automatically by Mage_Sales_Model_Order_Payment::refund() when the
     * creditmemo is saved from admin with "Refund" (doTransaction=true). If the
     * Mollie API call fails we throw, which rolls back the creditmemo save.
     *
     * TODO(agent-3): when giftcard method is added, skip online refund for it —
     * Mollie handles giftcards server-side.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function refund(\Maho\DataObject $payment, $amount): self
    {
        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');

        $molliePaymentId = (string) $payment->getAdditionalInformation('mollie_payment_id');
        if ($molliePaymentId === '') {
            Mage::throwException($helper->__(
                'Cannot refund via Mollie: no Mollie payment ID stored on this order. '
                . 'The original payment may never have completed. Use an offline credit memo instead.',
            ));
        }

        $order = $payment->getOrder();
        $storeId = (int) $order->getStoreId();
        $currencyCode = (string) $order->getOrderCurrencyCode();
        $incrementId = (string) $order->getIncrementId();

        try {
            $client = $helper->getApiClient($storeId);
            $molliePayment = $client->payments->get($molliePaymentId);

            $payload = [
                'amount' => [
                    'currency' => $currencyCode,
                    'value'    => $helper->formatAmount((float) $amount),
                ],
                'description' => 'Refund for order #' . $incrementId,
            ];

            $refund = $molliePayment->refund($payload);

            $refundId = (string) $refund->id;

            // Persist refund id list so we can recognise our own refunds when
            // Mollie sends the refund webhook back.
            $stored = $payment->getAdditionalInformation('mollie_refund_ids');
            $refundIds = [];
            if (is_string($stored) && $stored !== '') {
                /** @var Mage_Core_Helper_Data $coreHelper */
                $coreHelper = Mage::helper('core');
                try {
                    $decoded = $coreHelper->jsonDecode($stored);
                    if (is_array($decoded)) {
                        $refundIds = array_values(array_filter(
                            $decoded,
                            static fn($v): bool => is_string($v) && $v !== '',
                        ));
                    }
                } catch (\Throwable) {
                    $refundIds = [];
                }
            } elseif (is_array($stored)) {
                $refundIds = array_values(array_filter(
                    $stored,
                    static fn($v): bool => is_string($v) && $v !== '',
                ));
            }

            if (!in_array($refundId, $refundIds, true)) {
                $refundIds[] = $refundId;
            }

            /** @var Mage_Core_Helper_Data $coreHelper */
            $coreHelper = Mage::helper('core');
            $payment->setAdditionalInformation('mollie_refund_ids', $coreHelper->jsonEncode($refundIds));
            $payment->setAdditionalInformation('last_mollie_refund_id', $refundId);

            // Make the refund id visible on the transaction + credit memo.
            $payment->setTransactionId($refundId);
            $payment->setIsTransactionClosed(true);

            if ($helper->isDebugEnabled($storeId)) {
                Mage::log(
                    "Mollie refund: created refund {$refundId} for order #{$incrementId} "
                    . "amount={$payload['amount']['value']} {$currencyCode}",
                    Mage::LOG_INFO,
                    'mollie.log',
                );
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
            Mage::throwException($helper->__(
                'Mollie refund failed for order #%s: %s',
                $incrementId,
                $e->getMessage(),
            ));
        }

        return $this;
    }

    #[\Override]
    public function fetchTransactionInfo(\Mage_Payment_Model_Info $payment, $transactionId): array
    {
        $paymentId = $payment->getAdditionalInformation('mollie_payment_id');
        if (!$paymentId) {
            return [];
        }

        $storeId = (int) $payment->getOrder()->getStoreId();
        try {
            $client = $this->_getMollieHelper()->getApiClient($storeId);
            $molliePayment = $client->payments->get((string) $paymentId);
            /** @var Mage_Core_Helper_Data $helper */
            $helper = Mage::helper('core');
            return $helper->jsonDecode($helper->jsonEncode($molliePayment));
        } catch (\Throwable $e) {
            Mage::logException($e);
            return [];
        }
    }

    protected function _getMollieHelper(): Maho_Mollie_Helper_Data
    {
        /** @var Maho_Mollie_Helper_Data */
        return Mage::helper('maho_mollie');
    }

    /**
     * Per-method subclasses override this to pin the Mollie API "method" code
     * (e.g. 'ideal', 'bancontact'). Returning null keeps the generic gateway
     * behaviour where Mollie shows its full selector.
     */
    protected function getMollieMethodCode(): ?string
    {
        return null;
    }

    /**
     * Extra fields to merge into the Mollie Payments API create payload.
     *
     * Subclasses return method-specific keys such as `cardToken` (Components),
     * `issuer` (iDEAL), or `billingEmail`. Returning an empty array (default)
     * leaves the generic payload untouched and keeps the redirect flow.
     *
     * @return array<string, mixed>
     */
    protected function getExtraPaymentPayload(Mage_Sales_Model_Order $order): array
    {
        return [];
    }
}
