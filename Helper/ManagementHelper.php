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
use Adyen\Payment\Logger\AdyenLogger;

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
     * Logging instance
     *
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * ManagementHelper constructor.
     * @param StoreManager $storeManager
     * @param Data $adyenHelper
     * @param Config $configHelper
     */
    public function __construct(
        StoreManager $storeManager,
        Data $adyenHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param string $apiKey
     * @param bool $demoMode
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function getMerchantAccountsAndClientKey(string $apiKey, bool $demoMode)
    {
        $management = $this->getManagementApiService($apiKey, $demoMode ? 'test' : 'live');
        $merchantAccounts = [];
        $page = 1;
        $pageSize = 100;
        //get the merchant accounts using get /merchants.
        $responseMerchants = $management->merchantAccount->list(["pageSize" => $pageSize]);
        while (count($merchantAccounts) < $responseMerchants['itemsTotal']) {
            foreach ($responseMerchants['data'] as $merchantAccount) {
                $defaultDC = array_filter($merchantAccount['dataCenters'], function ($dc) {
                    return $dc['name'] = 'default';
                });
                $merchantAccounts[] = [
                    'name' => $merchantAccount['name'],
                    'liveEndpointPrefix' => !empty($defaultDC) ? $defaultDC[0]['livePrefix'] : ''
                ];
            }
            ++$page;
            if (isset($responseMerchants['_links']['next'])) {
                $responseMerchants = $management->merchantAccount->list(
                    ["pageSize" => $pageSize, "pageNumber" => $page]
                );
            }
        }
        $responseMe = $management->me->retrieve();

        return [
            'merchantAccounts' => $merchantAccounts,
            'clientKey' => $responseMe['clientKey'],
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
    public function getAllowedOrigins($apiKey, $environment)
    {
        $management = $this->getManagementApiService($apiKey, $environment);

        $response = $management->allowedOrigins->list();

        return array_column($response['data'], 'domain');
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    public function saveAllowedOrigin($apiKey, $environment, $domain)
    {
        $management = $this->getManagementApiService($apiKey, $environment);

        $management->allowedOrigins->create(['domain' => $domain]);
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    private function getManagementApiService($apiKey, $environment): Management
    {
        $storeId = $this->storeManager->getStore()->getId();
        if (preg_match('/^\*+$/', $apiKey)) {
            // API key contains '******', set to the previously saved config value
            $apiKey = $this->configHelper->getApiKey($environment);
        }
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $apiKey, $environment === 'test');

        return new Management($client);
    }

    /**
     * @param string $merchantId
     * @return mixed|string
     * @throws NoSuchEntityException
     */
    public function webhookTest(string $merchantId)
    {
        //this is what we send from the customer area too
        $params = ['types' => ['AUTHORISATION']];
        $storeId = $this->storeManager->getStore()->getId();
        $webhookId = $this->configHelper->getWebhookId($storeId);
        try {
            $client = $this->adyenHelper->initializeAdyenClient();
            $management = new Management($client);
            $response = $management->merchantWebhooks->test($merchantId, $webhookId, $params);
            $this->adyenLogger->addInfo(
                sprintf( 'response from webhook test %s',
                json_encode($response))
            );
            return $response;
        } catch (AdyenException $exception) {
            $this->adyenLogger->addError($exception->getMessage());
            return $exception->getMessage();
        }
    }
}
