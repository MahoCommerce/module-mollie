<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_PaymentFee_Calculator
{
    /**
     * Compute the Mollie payment fee in the base currency.
     *
     * Returns 0 when the global fee type is "disabled" or the method has not
     * opted in via its per-method `fee_enabled` flag. Caller is responsible for
     * adding the returned amount to the quote/order totals.
     */
    public function calculate(
        Mage_Sales_Model_Quote|Mage_Sales_Model_Order $source,
        ?string $methodCode = null,
    ): float {
        $storeId = $source->getStoreId() !== null ? (int) $source->getStoreId() : null;

        if ($methodCode === null || $methodCode === '') {
            $methodCode = $this->resolveMethodCode($source);
        }

        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');

        if (!$helper->isPaymentFeeEnabledForMethod($methodCode, $storeId)) {
            return 0.0;
        }

        $type           = (string) Mage::getStoreConfig('maho_mollie/payment_fee/fee_type', $storeId);
        $fixed          = (float) Mage::getStoreConfig('maho_mollie/payment_fee/fee_fixed', $storeId);
        $percent        = (float) Mage::getStoreConfig('maho_mollie/payment_fee/fee_percent', $storeId);
        $max            = (string) Mage::getStoreConfig('maho_mollie/payment_fee/fee_max', $storeId);
        $incShipping    = Mage::getStoreConfigFlag('maho_mollie/payment_fee/include_shipping', $storeId);
        $incDiscount    = Mage::getStoreConfigFlag('maho_mollie/payment_fee/include_discount', $storeId);

        if ($type === '' || $type === Maho_Mollie_Model_System_Config_Source_PaymentFee_Type::TYPE_DISABLED) {
            return 0.0;
        }

        $base = $this->computeBase($source, $incShipping, $incDiscount);

        $fee = 0.0;
        if ($type === Maho_Mollie_Model_System_Config_Source_PaymentFee_Type::TYPE_FIXED
            || $type === Maho_Mollie_Model_System_Config_Source_PaymentFee_Type::TYPE_COMBINED) {
            $fee += $fixed;
        }
        if ($type === Maho_Mollie_Model_System_Config_Source_PaymentFee_Type::TYPE_PERCENT
            || $type === Maho_Mollie_Model_System_Config_Source_PaymentFee_Type::TYPE_COMBINED) {
            $fee += ($base * $percent) / 100.0;
        }

        if ($max !== '') {
            $cap = (float) $max;
            if ($cap > 0 && $fee > $cap) {
                $fee = $cap;
            }
        }

        if ($fee < 0) {
            $fee = 0.0;
        }

        return round($fee, 4);
    }

    protected function resolveMethodCode(
        Mage_Sales_Model_Quote|Mage_Sales_Model_Order $source,
    ): ?string {
        $payment = $source->getPayment();
        if ($payment !== null && $payment !== false) {
            $code = (string) $payment->getMethod();
            if ($code !== '') {
                return $code;
            }
        }
        return null;
    }

    protected function computeBase(
        Mage_Sales_Model_Quote|Mage_Sales_Model_Order $source,
        bool $includeShipping,
        bool $includeDiscount,
    ): float {
        if ($source instanceof Mage_Sales_Model_Quote) {
            $address = $source->isVirtual()
                ? $source->getBillingAddress()
                : $source->getShippingAddress();

            $base = (float) $address->getBaseSubtotal();
            if ($includeShipping) {
                $base += (float) $address->getBaseShippingAmount();
            }
            if ($includeDiscount) {
                // Discount amount on a quote address is a negative number
                // (e.g. -10.00). Adding it reduces the fee base.
                $base += (float) $address->getBaseDiscountAmount();
            }
            return $base;
        }

        $base = (float) $source->getBaseSubtotal();
        if ($includeShipping) {
            $base += (float) $source->getBaseShippingAmount();
        }
        if ($includeDiscount) {
            $base += (float) $source->getBaseDiscountAmount();
        }
        return $base;
    }
}
