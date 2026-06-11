<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Mollie
 */

declare(strict_types=1);

class Maho_Mollie_Adminhtml_Mollie_CredentialsController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/config/maho_mollie';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('test');
        return parent::preDispatch();
    }

    /**
     * Ping the Mollie API using the submitted (or currently stored) credentials
     * and respond with JSON indicating success or failure.
     */
    public function testAction(): void
    {
        /** @var Maho_Mollie_Helper_Data $helper */
        $helper = Mage::helper('maho_mollie');
        /** @var Mage_Core_Helper_Data $coreHelper */
        $coreHelper = Mage::helper('core');

        $request   = $this->getRequest();
        $testmode  = (int) $request->getParam('testmode', -1);
        $postedKey = (string) $request->getParam(
            $testmode === 1 ? 'api_key_test' : 'api_key_live',
            '',
        );

        // Detect obscured value (all asterisks) — that means the user did not
        // re-enter the key, so fall back to the stored one.
        if ($postedKey !== '' && preg_match('/^\*+$/', $postedKey)) {
            $postedKey = '';
        }

        $apiKey = $postedKey;

        if ($apiKey === '') {
            if ($testmode === -1) {
                $testmode = $helper->isTestMode() ? 1 : 0;
            }
            $path = $testmode === 1
                ? 'maho_mollie/credentials/api_key_test'
                : 'maho_mollie/credentials/api_key_live';
            $stored = (string) Mage::getStoreConfig($path);
            if ($stored !== '') {
                // Stored value is encrypted (backend_model=system_config_backend_encrypted).
                $decrypted = (string) $coreHelper->decrypt($stored);
                $apiKey = $decrypted !== '' ? $decrypted : $stored;
            }
        }

        if ($apiKey === '') {
            $this->getResponse()->setBodyJson([
                'success' => false,
                'message' => Mage::helper('maho_mollie')->__(
                    'No Mollie API key is configured. Enter a key (or save one) and try again.',
                ),
            ]);
            return;
        }

        try {
            $client = new \Mollie\Api\MollieApiClient();
            $client->setApiKey($apiKey);
            // Light ping: fetches the list of methods enabled on the Mollie account.
            $methods = $client->methods->allEnabled();
            $count = is_countable($methods) ? count($methods) : 0;

            $this->getResponse()->setBodyJson([
                'success' => true,
                'message' => Mage::helper('maho_mollie')->__(
                    'Connection OK. Mollie returned %d active payment method(s).',
                    $count,
                ),
            ]);
        } catch (\Throwable $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson([
                'success' => false,
                'message' => Mage::helper('maho_mollie')->__(
                    'Mollie API error: %s',
                    $e->getMessage(),
                ),
            ]);
        }
    }

    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ADMIN_RESOURCE);
    }
}
