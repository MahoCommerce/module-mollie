<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_Method_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'mollie';

    protected $_formBlockType = 'maho_mollie/form';
    protected $_infoBlockType = 'maho_mollie/info';

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
        $stateObject->setData('state', Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setData('status', 'pending_payment');
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
        // TODO: port from M2 Service/Mollie/Payments.php — call $client->payments->create()
        // with amount, currency, description, redirectUrl (mollie/payment/return),
        // webhookUrl (mollie/webhook/index), metadata (order_increment_id), locale, method.
        // Persist the returned $payment->id to payment additional_information as 'mollie_payment_id'.

        Mage::throwException($this->_getMollieHelper()->__(
            'Mollie payment creation is not yet implemented (order #%s).',
            $order->getIncrementId(),
        ));
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
     * @param Mage_Sales_Model_Order_Payment $payment
     */
    #[\Override]
    public function refund(\Maho\DataObject $payment, $amount): self
    {
        // TODO: port from M2 Service/Mollie/Refund.php — load the Mollie Payment by id
        // and call $payment->refund(['amount' => ...]).

        Mage::throwException($this->_getMollieHelper()->__('Mollie refund is not yet implemented.'));
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
}
