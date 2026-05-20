<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Block_Form_Creditcard extends Maho_Mollie_Block_Form
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('mollie/form/creditcard.phtml');
    }

    public function isComponentsEnabled(): bool
    {
        $storeId = $this->getCurrentStoreId();
        if (!Mage::getStoreConfigFlag('payment/mollie_creditcard/use_components', $storeId)) {
            return false;
        }
        return $this->getProfileId() !== '';
    }

    public function getProfileId(): string
    {
        return trim((string) Mage::getStoreConfig('payment/mollie_creditcard/profile_id', $this->getCurrentStoreId()));
    }

    public function isTestMode(): bool
    {
        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        return $helper->isTestMode($this->getCurrentStoreId());
    }

    public function getMollieLocale(): string
    {
        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        return $helper->getLocale($this->getCurrentStoreId());
    }

    private function getCurrentStoreId(): ?int
    {
        $store = Mage::app()->getStore();
        return $store !== null ? (int) $store->getId() : null;
    }
}
