<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_System_Config_Source_PaymentFee_Type
{
    public const TYPE_DISABLED = 'disabled';
    public const TYPE_FIXED    = 'fixed';
    public const TYPE_PERCENT  = 'percent';
    public const TYPE_COMBINED = 'combined';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');

        return [
            ['value' => self::TYPE_DISABLED, 'label' => $helper->__('Disabled')],
            ['value' => self::TYPE_FIXED,    'label' => $helper->__('Fixed amount')],
            ['value' => self::TYPE_PERCENT,  'label' => $helper->__('Percentage')],
            ['value' => self::TYPE_COMBINED, 'label' => $helper->__('Fixed amount + Percentage')],
        ];
    }
}
