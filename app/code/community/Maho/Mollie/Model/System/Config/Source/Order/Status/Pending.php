<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Order statuses belonging to the pending_payment state. Maho core ships no
 * built-in source scoped to this state (its `_new` variant covers STATE_NEW
 * only), and Mollie places orders in STATE_PENDING_PAYMENT before the
 * customer returns from the gateway, so we need our own list here.
 */
class Maho_Mollie_Model_System_Config_Source_Order_Status_Pending extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    /** @var mixed */
    protected $_stateStatuses = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
}
