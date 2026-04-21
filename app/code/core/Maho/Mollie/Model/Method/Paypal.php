<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_Method_Paypal extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_paypal';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'paypal';
    }
}
