<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

// TODO: giftcard refunds are handled server-side by Mollie — revisit when Orders API lands.
class Maho_Mollie_Model_Method_Giftcard extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_giftcard';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'giftcard';
    }
}
