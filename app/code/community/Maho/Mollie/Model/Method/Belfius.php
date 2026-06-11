<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

class Maho_Mollie_Model_Method_Belfius extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_belfius';

    protected ?string $_requiredCurrency = 'EUR';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'belfius';
    }
}
