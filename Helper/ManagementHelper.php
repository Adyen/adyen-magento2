<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

/**
 * Class ManagementHelper
 * @package Adyen\Payment\Helper
 */

use Adyen\AdyenException;
use Adyen\Service\Management;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManager;

class ManagementHelper
{
    /**
     * @var Data
     */
    private $adyenHelper;
    /**
     * @var StoreManager
     */
    private $storeManager;
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * ManagementHelper constructor.
     * @param StoreManager $storeManager
     * @param Data $adyenHelper
     * @param Config $configHelper
     */
    public function __construct(StoreManager $storeManager, Data $adyenHelper, Config $configHelper)
    {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->configHelper = $configHelper;
    }

    /**
     * @param string $xapikey
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function getMerchantAccountWithApikey(string $xapikey, ?bool $demoMode = null): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $xapikey, $demoMode);
        $management = new Management($client);
        $response = $management->me->retrieve();
        return [
            'clientKey' => $response['clientKey'],
            'associatedMerchantAccounts' => $response['associatedMerchantAccounts'],
        ];
    }

    /**
     * @param string $apiKey
     * @param string $merchantId
     * @param string $username
     * @param string $password
     * @param string $url
     * @param bool $demoMode
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function setupWebhookCredentials(
        string $apiKey,
        string $merchantId,
        string $username,
        string $password,
        string $url,
        bool $demoMode
    ) {
        $storeId = $this->storeManager->getStore()->getId();
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $apiKey, $demoMode);

        $management = new Management($client);
        $params = [
            'url' => $url,
            'username' => $username,
            'password' => $password,
            'communicationFormat' => 'json',
            'active' => true,
        ];
        $webhookId = $this->configHelper->getWebhookId($storeId);
        if (!empty($webhookId)) {
            $management->merchantWebhooks->update($merchantId, $webhookId, $params);
        } else {
            $params['type'] = 'standard';
            $response = $management->merchantWebhooks->create($merchantId, $params);
            // save webhook_id to configuration
            $webhookId = $response['id'];
            $this->configHelper->setConfigData($webhookId, 'webhook_id', Config::XML_ADYEN_ABSTRACT_PREFIX);
        }

        // generate hmac key and save
        $response = $management->merchantWebhooks->generateHmac($merchantId, $webhookId);
        $hmac = $response['hmacKey'];
        $mode = $demoMode ? 'test' : 'live';
        $this->configHelper->setConfigData($hmac, 'notification_hmac_key_' . $mode, Config::XML_ADYEN_ABSTRACT_PREFIX);
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    public function getAllowedOrigins($apiKey, $demoMode)
    {
        $management = $this->getManagementApiService($apiKey, $demoMode);

        $response = $management->allowedOrigins->list();

        return array_column($response['data'], 'domain');
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    public function saveAllowedOrigin($apiKey, $demoMode, $domain)
    {
        $management = $this->getManagementApiService($apiKey, $demoMode);

        $management->allowedOrigins->create(['domain' => $domain]);
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    private function getManagementApiService($apiKey, $demoMode): Management
    {
        $storeId = $this->storeManager->getStore()->getId();
        if (preg_match('/^\*+$/', $apiKey)) {
            // API key contains '******', set to the previously saved config value
            $apiKey = $this->configHelper->getApiKey($demoMode);
        }
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $apiKey, $demoMode);

        return new Management($client);
    }
}
