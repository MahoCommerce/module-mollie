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
 * Apple Pay via Mollie — redirect flow only.
 *
 * TODO(orders-api): port to Orders API when that path is added.
 * Express-button / domain-association / Apple Pay JS session are out of
 * scope here; this is the "pay with Apple Pay via redirect" method choice.
 */
class Maho_Mollie_Model_Method_Applepay extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_applepay';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'applepay';
    }
}
