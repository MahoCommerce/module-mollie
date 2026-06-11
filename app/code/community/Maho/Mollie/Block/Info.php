<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

class Maho_Mollie_Block_Info extends Mage_Payment_Block_Info
{
    #[\Override]
    protected function _prepareSpecificInformation($transport = null): \Maho\DataObject
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $payment = $this->getInfo();
        $helper = Mage::helper('maho_mollie');

        $data = [];

        $paymentId = $payment->getAdditionalInformation('mollie_payment_id');
        if ($paymentId) {
            $data[$helper->__('Mollie Payment ID')] = $paymentId;
        }

        $method = $payment->getAdditionalInformation('mollie_method');
        if ($method) {
            $data[$helper->__('Mollie Payment Method')] = $method;
        }

        return $transport->addData($data);
    }
}
