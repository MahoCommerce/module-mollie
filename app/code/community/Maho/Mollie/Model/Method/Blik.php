<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

class Maho_Mollie_Model_Method_Blik extends Maho_Mollie_Model_Method_Standard
{
    #[\Override]
    protected $_code = 'mollie_blik';

    #[\Override]
    protected ?string $_requiredCurrency = 'PLN';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'blik';
    }
}
