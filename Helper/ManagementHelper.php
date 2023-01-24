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
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManager;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Encryption\EncryptorInterface;

class ManagementHelper
{
    /**
     * @var Data
     */
    private $dataHelper;
    /**
     * @var StoreManager
     */
    private $storeManager;
    /**
     * @var Config
     */
    private $configHelper;
    
    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * Logging instance
     *
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * ManagementHelper constructor.
     * @param StoreManager $storeManager
     * @param EncryptorInterface $encryptor
     * @param Data $dataHelper
     * @param Config $configHelper
     * @param AdyenLogger $adyenLogger
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        StoreManager $storeManager,
        EncryptorInterface $encryptor,
        Data $dataHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger,
        ManagerInterface $messageManager
    ) {
        $this->dataHelper = $dataHelper;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
        $this->messageManager = $messageManager;
    }

    /**
     * @param string $apiKey
     * @param bool $demoMode
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function getMerchantAccountsAndClientKey(string $apiKey, bool $demoMode): array
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
                    return $dc['name'] === 'default';
                });
                $merchantAccounts[] = [
                    'id' => $merchantAccount['id'],
                    'name' => $merchantAccount['id'],
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

        $currentMerchantAccount = $this->configHelper->getMerchantAccount($this->storeManager->getStore()->getId());

        return [
            'merchantAccounts' => $merchantAccounts,
            'clientKey' => $responseMe['clientKey'] ?? '',
            'currentMerchantAccount' => $currentMerchantAccount
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
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
        $client = $this->dataHelper->initializeAdyenClient($storeId, $apiKey, null, $demoMode);

        $management = new Management($client);
        $params = [
            'url' => $url,
            'username' => $username,
            'password' => $password,
            'communicationFormat' => 'json',
            'active' => true,
            'additionalSettings' =>
                [
                    'includeEventCodes' => [
                        'AUTHORISATION',
                        'PENDING',
                        'REFUND',
                        'REFUND_FAILED',
                        'CANCEL_OR_REFUND',
                        'CAPTURE',
                        'CAPTURE_FAILED',
                        'CANCELLATION',
                        'HANDLED_EXTERNALLY',
                        'MANUAL_REVIEW_ACCEPT',
                        'MANUAL_REVIEW_REJECT',
                        "RECURRING_CONTRACT",
                        "REPORT_AVAILABLE",
                        "ORDER_CLOSED",
                        "OFFER_CLOSED"
                    ]
                ]
        ];
        $webhookId = $this->configHelper->getWebhookId($storeId);
        $savedMerchantAccount = $this->configHelper->getMerchantAccount($storeId);

        try {
            // reuse saved webhookId if merchant account is the same.
            if (!empty($webhookId) && $merchantId === $savedMerchantAccount) {
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
            $hmacKey = $response['hmacKey'];
            $hmac = $this->encryptor->encrypt($hmacKey);
            $mode = $demoMode ? 'test' : 'live';
            $this->configHelper->setConfigData($hmac, 'notification_hmac_key_' . $mode, Config::XML_ADYEN_ABSTRACT_PREFIX);
        } catch (\Exception $exception) {
            $this->adyenLogger->error($exception->getMessage());

            if (!$demoMode) {
                throw $exception;
            }

            $this->messageManager->addErrorMessage(__("Credentials saved but webhook and HMAC key couldn't be generated! Please check the error logs."));
        }
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    public function getAllowedOrigins($apiKey, $environment): array
    {
        $management = $this->getManagementApiService($apiKey, $environment);

        $response = $management->allowedOrigins->list();

        return !empty($response) ? array_column($response['data'], 'domain') : [];
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    public function saveAllowedOrigin($apiKey, $environment, $domain): void
    {
        $management = $this->getManagementApiService($apiKey, $environment);

        $management->allowedOrigins->create(['domain' => $domain]);
    }

    /**
     * @throws AdyenException|NoSuchEntityException
     */
    private function getManagementApiService(string $apiKey, $environment): Management
    {
        $storeId = $this->storeManager->getStore()->getId();
        if (preg_match('/^\*+$/', $apiKey)) {
            // API key contains '******', set to the previously saved config value
            $apiKey = $this->configHelper->getApiKey($environment);
        }
        $client = $this->dataHelper->initializeAdyenClient($storeId, $apiKey, null, $environment === 'test');

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
        $params = ["types" => ["AUTHORISATION"]];
        $storeId = $this->storeManager->getStore()->getId();
        $webhookId = $this->configHelper->getWebhookId($storeId);
        try {
            $client = $this->dataHelper->initializeAdyenClient();
            $management = new Management($client);
            $response = $management->merchantWebhooks->test($merchantId, $webhookId, $params);
            $this->adyenLogger->info(
                sprintf( 'response from webhook test %s',
                json_encode($response))
            );
            return $response;
        } catch (AdyenException $exception) {
            $this->adyenLogger->error($exception->getMessage());
            return $exception->getMessage();
        }
    }
}
