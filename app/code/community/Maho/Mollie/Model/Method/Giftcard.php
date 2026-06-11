<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

// TODO: giftcard refunds are handled server-side by Mollie — revisit when Orders API lands.
class Maho_Mollie_Model_Method_Giftcard extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_giftcard';

    protected ?string $_requiredCurrency = 'EUR';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'giftcard';
    }
}
