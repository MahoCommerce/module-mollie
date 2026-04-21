<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Install script — adds Mollie payment fee columns to the quote address,
 * order, invoice, and creditmemo flat tables. Pattern mirrors Mage_Weee's
 * install-1.6.0.0.php: use $installer->addAttribute(entity, code, attr) which
 * on the sales resource setup (Mage_Sales_Model_Resource_Setup) maps to a
 * flat-column addColumn with the correct DECIMAL(12,4) definition.
 *
 * @var Mage_Sales_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$installer->addAttribute('quote_address', 'payment_fee_amount', ['type' => 'decimal']);
$installer->addAttribute('quote_address', 'base_payment_fee_amount', ['type' => 'decimal']);

$installer->addAttribute('order', 'payment_fee_amount', ['type' => 'decimal']);
$installer->addAttribute('order', 'base_payment_fee_amount', ['type' => 'decimal']);

$installer->addAttribute('invoice', 'payment_fee_amount', ['type' => 'decimal']);
$installer->addAttribute('invoice', 'base_payment_fee_amount', ['type' => 'decimal']);

$installer->addAttribute('creditmemo', 'payment_fee_amount', ['type' => 'decimal']);
$installer->addAttribute('creditmemo', 'base_payment_fee_amount', ['type' => 'decimal']);

$installer->endSetup();
