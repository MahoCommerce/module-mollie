<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

class Maho_Mollie_Model_Method_Banktransfer extends Maho_Mollie_Model_Method_Standard
{
    protected $_code = 'mollie_banktransfer';

    #[\Override]
    protected function getMollieMethodCode(): ?string
    {
        return 'banktransfer';
    }

    /**
     * A bank transfer can take days to settle, and the customer has to act on the
     * order to pay it at all, so the confirmation goes out at placement rather than
     * on capture — matching how Maho treats offline bank transfer / check-money-order.
     */
    #[\Override]
    public function shouldSendOrderEmailOnPlacement(): bool
    {
        return true;
    }
}
