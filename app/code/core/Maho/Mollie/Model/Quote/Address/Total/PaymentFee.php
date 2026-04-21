<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_Quote_Address_Total_PaymentFee extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('payment_fee');
    }

    #[\Override]
    public function collect(Mage_Sales_Model_Quote_Address $address): self
    {
        parent::collect($address);

        $address->setPaymentFeeAmount(0);
        $address->setBasePaymentFeeAmount(0);

        $quote = $address->getQuote();
        if (!$quote instanceof Mage_Sales_Model_Quote) {
            return $this;
        }

        // Only attach the fee to the address that owns the grand total:
        // shipping address for physical orders, billing address for virtual.
        $isVirtual = $quote->isVirtual();
        $type = (string) $address->getAddressType();
        if ($isVirtual) {
            if ($type !== Mage_Sales_Model_Quote_Address::TYPE_BILLING) {
                return $this;
            }
        } else {
            if ($type !== Mage_Sales_Model_Quote_Address::TYPE_SHIPPING) {
                return $this;
            }
        }

        /** @var Maho_Mollie_Model_PaymentFee_Calculator $calculator */
        $calculator = Mage::getSingleton('maho_mollie/paymentFee_calculator');
        $fee = $calculator->calculate($quote);

        if ($fee <= 0) {
            return $this;
        }

        $store = $quote->getStore();
        $displayFee = $store !== null ? (float) $store->convertPrice($fee, false) : $fee;

        $address->setPaymentFeeAmount($displayFee);
        $address->setBasePaymentFeeAmount($fee);

        $address->setGrandTotal((float) $address->getGrandTotal() + $displayFee);
        $address->setBaseGrandTotal((float) $address->getBaseGrandTotal() + $fee);

        return $this;
    }

    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address): self
    {
        $amount = (float) $address->getPaymentFeeAmount();
        if ($amount <= 0) {
            return $this;
        }

        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');

        $address->addTotal([
            'code'  => $this->getCode(),
            'title' => $helper->__('Payment fee'),
            'value' => $amount,
        ]);

        return $this;
    }
}
