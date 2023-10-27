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
use Adyen\ConnectionException;
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
     * @param Management $managementApiService
     * @return array
     * @throws AdyenException | ConnectionException
     * @throws NoSuchEntityException
     */
    public function getMerchantAccountsAndClientKey(Management $managementApiService): array
    {
        $merchantAccounts = [];
        $page = 1;
        $pageSize = 100;
        //get the merchant accounts using get /merchants.
        $responseMerchants = $managementApiService->merchantAccount->list(["pageSize" => $pageSize]);
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
                $responseMerchants = $managementApiService->merchantAccount->list(
                    ["pageSize" => $pageSize, "pageNumber" => $page]
                );
            }
        }
        $responseMe = $managementApiService->me->retrieve();

        $currentMerchantAccount = $this->configHelper->getMerchantAccount($this->storeManager->getStore()->getId());

        return [
            'merchantAccounts' => $merchantAccounts,
            'clientKey' => $responseMe['clientKey'] ?? '',
            'currentMerchantAccount' => $currentMerchantAccount
        ];
    }

    /**
     * @param string $merchantId
     * @param string $username
     * @param string $password
     * @param string $url
     * @param bool $demoMode
     * @param Management $managementApiService
     * @return string|null
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function setupWebhookCredentials(
        string $merchantId,
        string $username,
        string $password,
        string $url,
        bool $demoMode,
        Management $managementApiService
    ): ?string {
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

        $storeId = $this->storeManager->getStore()->getId();
        $webhookId = $this->configHelper->getWebhookId($storeId);
        $savedMerchantAccount = $this->configHelper->getMerchantAccount($storeId);
        // Try to reuse saved webhookId if merchant account is the same.
        if (!empty($webhookId) && $merchantId === $savedMerchantAccount) {
            try {
                $response = $managementApiService->merchantWebhooks->update($merchantId, $webhookId, $params);
            } catch (AdyenException $exception){
                $this->adyenLogger->error($exception->getMessage());
            }
        }

        // If update request fails, meas that webhook has been removed. Create new webhook.
        if (!isset($response) || empty($webhookId)) {
            try {
                $params['type'] = 'standard';
                $response = $managementApiService->merchantWebhooks->create($merchantId, $params);
                // save webhook_id to configuration
                $webhookId = $response['id'];
                $this->configHelper->setConfigData($webhookId, 'webhook_id', Config::XML_ADYEN_ABSTRACT_PREFIX);
            } catch (\Exception $exception) {
                $this->adyenLogger->error($exception->getMessage());
            }
        }

        if (!empty($webhookId)) {
            try {
                // generate hmac key and save
                $response = $managementApiService->merchantWebhooks->generateHmac($merchantId, $webhookId);
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
        return $webhookId;
    }

    /**
     * @param Management $managementApiService
     * @return array
     * @throws AdyenException
     */
    public function getAllowedOrigins(Management $managementApiService): array
    {
        $response = $managementApiService->allowedOrigins->list();

        return !empty($response) ? array_column($response['data'], 'domain') : [];
    }

    /**
     * @param Management $managementApiService
     * @param string $domain
     * @return void
     * @throws AdyenException
     */
    public function saveAllowedOrigin(Management $managementApiService, string $domain): void
    {
        $managementApiService->allowedOrigins->create(['domain' => $domain]);
    }

    /**
     * @param string $merchantId
     * @param string $webhookId
     * @param Management $managementApiService
     * @return mixed|string
     */
    public function webhookTest(string $merchantId, string $webhookId, Management $managementApiService)
    {
        $params = [
            'types' => [
                'AUTHORISATION'
            ]
        ];

        try {
            $response = $managementApiService->merchantWebhooks->test($merchantId, $webhookId, $params);

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

    /**
     * @param string $apiKey
     * @param bool $demoMode
     * @return Management
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function getManagementApiService(string $apiKey, bool $demoMode): Management
    {
        $environment = $demoMode ? 'test' : 'live';
        $storeId = $this->storeManager->getStore()->getId();

        if (preg_match('/^\*+$/', $apiKey)) {
            // API key contains '******', set to the previously saved config value
            $apiKey = $this->configHelper->getApiKey($environment);
        }

        $client = $this->dataHelper->initializeAdyenClient($storeId, $apiKey, null, $environment === 'test');

        return new Management($client);
    }
}
