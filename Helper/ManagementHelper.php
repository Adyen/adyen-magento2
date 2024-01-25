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
use Adyen\Client;
use Adyen\ConnectionException;
use Adyen\Model\Management\CreateAllowedOriginRequest;
use Adyen\Model\Management\CreateMerchantWebhookRequest;
use Adyen\Model\Management\TestWebhookRequest;
use Adyen\Model\Management\TestWebhookResponse;
use Adyen\Model\Management\UpdateMerchantWebhookRequest;
use Adyen\Service\Management\AccountMerchantLevelApi;
use Adyen\Service\Management\MyAPICredentialApi;
use Adyen\Service\Management\WebhooksMerchantLevelApi;
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
        StoreManager       $storeManager,
        EncryptorInterface $encryptor,
        Data               $dataHelper,
        Config             $configHelper,
        AdyenLogger        $adyenLogger,
        ManagerInterface   $messageManager
    )
    {
        $this->dataHelper = $dataHelper;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
        $this->messageManager = $messageManager;
    }

    /**
     * @throws AdyenException | ConnectionException | NoSuchEntityException
     */
    public function getMerchantAccountsAndClientKey(Client $client): array
    {
        $merchantAccounts = [];
        $page = 1;
        $pageSize = 100;
        //get the merchant accounts using get /merchants.
        $service = new AccountMerchantLevelApi($client);
        $responseMerchantsObj = $service->listMerchantAccounts(["pageSize" => $pageSize]);
        $responseMerchants = (array)$responseMerchantsObj->jsonSerialize();
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
                $responseMerchantsObj = $service->listMerchantAccounts(
                    ["pageSize" => $pageSize, "pageNumber" => $page]
                );
                $responseMerchants = (array)$responseMerchantsObj->jsonSerialize();
            }
        }
        $myApiService = new MyAPICredentialApi($client);
        $responseMeObj = $myApiService->getApiCredentialDetails();
        $responseMe = (array) $responseMeObj->jsonSerialize();

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
     * @param Client $client
     * @return string|null
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function setupWebhookCredentials(
        string               $merchantId,
        string               $username,
        string               $password,
        string               $url,
        bool                 $demoMode,
        Client               $client
    ): ?string
    {
        $service = new WebhooksMerchantLevelApi($client);
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

                $updateRequest = new UpdateMerchantWebhookRequest($params);
                $response = $service->updateWebhook($merchantId, $webhookId, $updateRequest);

            } catch (AdyenException $exception) {
                $this->adyenLogger->error($exception->getMessage());
            }
        }

        // If update request fails, meas that webhook has been removed. Create new webhook.
        if (!isset($response) || empty($webhookId)) {
            try {
                $params['type'] = 'standard';
                $response = $service->setUpWebhook($merchantId, new CreateMerchantWebhookRequest($params));
                // save webhook_id to configuration
                $webhookId = $response->getId();
                $this->configHelper->setConfigData($webhookId, 'webhook_id', Config::XML_ADYEN_ABSTRACT_PREFIX);
            } catch (\Exception $exception) {
                $this->adyenLogger->error($exception->getMessage());
            }
        }

        if (!empty($webhookId)) {
            try {
                // generate hmac key and save
                $response = $service->generateHmacKey($merchantId, $webhookId);
                $hmacKey = $response->getHmacKey();
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
     * @throws AdyenException
     */
    public function getAllowedOrigins(Client $client): array
    {
        $service = new MyAPICredentialApi($client);

        $responseObj = $service->getAllowedOrigins();
        $response = (array)$responseObj->jsonSerialize();

        return !empty($response) ? array_column($response['data'], 'domain') : [];
    }

    /**
     * @throws AdyenException
     */
    public function saveAllowedOrigin(Client $client, string $domain): void
    {
        $service = new MyAPICredentialApi($client);
        $service->addAllowedOrigin(new CreateAllowedOriginRequest(['domain' => $domain]));
    }

    public function webhookTest(string $merchantId, string $webhookId, Client $client): ?TestWebhookResponse
    {
        $service = new WebhooksMerchantLevelApi($client);
        $testWebhookRequest = new TestWebhookRequest(['types' => ['AUTHORISATION']]);
        $response = null;
        try {
            $response = $service->testWebhook($merchantId, $webhookId, $testWebhookRequest);
            $this->adyenLogger->info(sprintf('response from webhook test %s', $response));
        } catch (AdyenException $exception) {
            $this->adyenLogger->error($exception->getMessage());
        }

        return $response;
    }

    /**
     * @param string $apiKey
     * @param bool $demoMode
     * @return Client
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function getAdyenApiClient(string $apiKey, bool $demoMode): Client
    {
        $environment = $demoMode ? 'test' : 'live';
        $storeId = $this->storeManager->getStore()->getId();

        if (preg_match('/^\*+$/', $apiKey)) {
            // API key contains '******', set to the previously saved config value
            $apiKey = $this->configHelper->getApiKey($environment);
        }

        $client = $this->dataHelper->initializeAdyenClient($storeId, $apiKey, null, $environment === 'test');

        return $client;
    }
}
