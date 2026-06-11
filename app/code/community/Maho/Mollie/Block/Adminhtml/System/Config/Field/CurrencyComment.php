<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

/**
 * Sets the "Only available… {currency}" comment on a single-currency Mollie
 * method's Enabled field, reading the currency from the method's own
 * _requiredCurrency property so the literal currency code lives in only one
 * place.
 */
class Maho_Mollie_Block_Adminhtml_System_Config_Field_CurrencyComment extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $currency = $this->_resolveCurrency($element);
        if ($currency !== null) {
            /** @var Maho_Mollie_Helper_Data $helper */
            $helper = Mage::helper('maho_mollie');
            $element->setComment(
                $helper->__(
                    'Only available at checkout when the quote currency is %s; hidden otherwise.',
                    $currency,
                ),
            );
        }
        return parent::render($element);
    }

    private function _resolveCurrency(\Maho\Data\Form\Element\AbstractElement $element): ?string
    {
        if (!preg_match('#(mollie_[a-z0-9]+)#', (string) $element->getHtmlId(), $m)) {
            return null;
        }
        /** @var Mage_Payment_Helper_Data $paymentHelper */
        $paymentHelper = Mage::helper('payment');
        try {
            $method = $paymentHelper->getMethodInstance($m[1]);
        } catch (\Throwable) {
            return null;
        }
        if (!$method instanceof Maho_Mollie_Model_Method_Standard) {
            return null;
        }
        $value = (new \ReflectionProperty($method, '_requiredCurrency'))->getValue($method);
        return is_string($value) && $value !== '' ? $value : null;
    }
}
