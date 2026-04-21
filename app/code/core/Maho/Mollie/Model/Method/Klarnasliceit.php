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
 * Klarna "Slice it" via Mollie.
 *
 * TODO(orders-api): port to Orders API when that path is added. Klarna needs
 * line-item detail in production; the Payments API path works in test mode and
 * is kept as a stop-gap so the method appears in checkout.
 */
class Maho_Mollie_Model_Method_Klarnasliceit extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_klarnasliceit';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'klarnasliceit';
    }
}
