<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_Method_Creditcard extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_creditcard';

    protected $_formBlockType = 'maho_mollie/form_creditcard';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'creditcard';
    }

    /**
     * Copy the Mollie Components card token from the checkout request into
     * additional_information so it survives until createPayment runs.
     *
     * @param array<string, mixed>|\Maho\DataObject $data
     */
    #[\Override]
    public function assignData($data): self
    {
        parent::assignData($data);

        $token = '';
        if ($data instanceof \Maho\DataObject) {
            $token = (string) $data->getData('mollie_card_token');
        } elseif (is_array($data) && isset($data['mollie_card_token'])) {
            $token = (string) $data['mollie_card_token'];
        }

        $info = $this->getInfoInstance();
        if ($info !== null) {
            $info->setAdditionalInformation('mollie_card_token', $token);
        }

        Mage::log(
            'Mollie Creditcard assignData: token=' . ($token !== '' ? substr($token, 0, 12) . '…' : '(empty)')
            . ' data_keys=' . implode(',', is_array($data) ? array_keys($data) : ($data instanceof \Maho\DataObject ? array_keys($data->getData()) : []))
            . ' info_class=' . ($info !== null ? get_class($info) : 'null'),
            Mage::LOG_INFO,
            'mollie.log',
        );

        return $this;
    }

    /**
     * Inject the Components cardToken so Mollie skips its hosted card form and
     * the customer stays on our success page (no redirect on success).
     *
     * If no token was captured (Components disabled, JS blocked, fallback), we
     * return an empty payload and the parent falls through to the redirect flow.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getExtraPaymentPayload(Mage_Sales_Model_Order $order): array
    {
        $payment = $order->getPayment();
        if (!$payment instanceof Mage_Sales_Model_Order_Payment) {
            Mage::log('Mollie Creditcard getExtraPaymentPayload: order has no payment', Mage::LOG_INFO, 'mollie.log');
            return [];
        }

        $token = (string) $payment->getAdditionalInformation('mollie_card_token');
        Mage::log(
            'Mollie Creditcard getExtraPaymentPayload: order=' . $order->getIncrementId()
            . ' token=' . ($token !== '' ? substr($token, 0, 12) . '…' : '(empty)')
            . ' all_additional_info=' . json_encode($payment->getAdditionalInformation()),
            Mage::LOG_INFO,
            'mollie.log',
        );

        if ($token === '') {
            return [];
        }

        return ['cardToken' => $token];
    }
}
