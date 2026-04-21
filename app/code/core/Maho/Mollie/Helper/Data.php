<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Maho_Mollie';

    public function isTestMode(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('maho_mollie/credentials/testmode', $storeId);
    }

    public function getApiKey(?int $storeId = null): string
    {
        $path = $this->isTestMode($storeId)
            ? 'maho_mollie/credentials/api_key_test'
            : 'maho_mollie/credentials/api_key_live';
        return (string) Mage::getStoreConfig($path, $storeId);
    }

    public function hasCredentials(?int $storeId = null): bool
    {
        return $this->getApiKey($storeId) !== '';
    }

    /**
     * Format an amount as Mollie expects it in API calls: string with exactly two decimals,
     * dot as decimal separator, no thousands separator. E.g. "10.00", "1234.56".
     */
    public function formatAmount(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Map store locale to a Mollie-supported locale code.
     *
     * Mollie accepts: en_US, en_GB, nl_NL, nl_BE, fr_FR, fr_BE, de_DE, de_AT, de_CH,
     * es_ES, ca_ES, pt_PT, it_IT, nb_NO, sv_SE, fi_FI, da_DK, is_IS, hu_HU, pl_PL,
     * lv_LV, lt_LT. Falls back to 'en_US'.
     */
    public function getLocale(?int $storeId = null): string
    {
        $supported = [
            'en_US', 'en_GB', 'nl_NL', 'nl_BE', 'fr_FR', 'fr_BE', 'de_DE', 'de_AT', 'de_CH',
            'es_ES', 'ca_ES', 'pt_PT', 'it_IT', 'nb_NO', 'sv_SE', 'fi_FI', 'da_DK', 'is_IS',
            'hu_HU', 'pl_PL', 'lv_LV', 'lt_LT',
        ];
        $localeCode = (string) Mage::getStoreConfig('general/locale/code', $storeId);
        $locale = str_replace('-', '_', $localeCode);
        return in_array($locale, $supported, true) ? $locale : 'en_US';
    }

    /**
     * Return a configured Mollie API client for the given store scope.
     *
     * @throws Mage_Core_Exception
     */
    public function getApiClient(?int $storeId = null): \Mollie\Api\MollieApiClient
    {
        $apiKey = $this->getApiKey($storeId);
        if ($apiKey === '') {
            Mage::throwException($this->__('Mollie API key is not configured.'));
        }

        $client = new \Mollie\Api\MollieApiClient();
        $client->setApiKey($apiKey);
        return $client;
    }
}
