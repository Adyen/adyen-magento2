<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Config as HttpClientConfig;
use Adyen\Environment;
use Adyen\Model\Management\AllowedOrigin;
use Adyen\Model\Management\AllowedOriginsResponse;
use Adyen\Model\Management\GenerateHmacKeyResponse;
use Adyen\Model\Management\ListMerchantResponse;
use Adyen\Model\Management\MeApiCredential;
use Adyen\Model\Management\TestWebhookResponse;
use Adyen\Model\Management\Webhook;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\ManagementHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Management\AccountMerchantLevelApi;
use Adyen\Service\Management\MyAPICredentialApi;
use Adyen\Service\Management\WebhooksMerchantLevelApi;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManager;

class ManagementHelperTest extends AbstractAdyenTestCase
{
    private Client $clientMock;

    public function setUp(): void
    {
        $this->clientMock = $this->createMock(Client::class);
        $this->clientMock->expects($this->any())
            ->method('getConfig')
            ->willReturn(new \Adyen\Config(['environment' => 'test']));
    }

    public function testGetMerchantAccountsAndClientKey()
    {
        $merchantAccountListResponseJson = <<<JSON
            {
                "_links": {
                    "first": {
                        "href": "https:\/\/management-test.adyen.com\/v1\/merchants?pageNumber=1&pageSize=100"
                    },
                    "last": {
                        "href": "https:\/\/management-test.adyen.com\/v1\/merchants?pageNumber=1&pageSize=100"
                    },
                    "self": {
                        "href": "https:\/\/management-test.adyen.com\/v1\/merchants?pageNumber=1&pageSize=100"
                    },
                    "next": {
                        "href": "https:\/\/management-test.adyen.com\/v1\/merchants?pageNumber=2&pageSize=100"
                    }
                },
                "itemsTotal": 2,
                "pagesTotal": 1,
                "data": [
                    {
                        "id": "MagentoTest",
                        "name": "MagentoTest",
                        "companyId": "TestCompany",
                        "captureDelay": "immediate",
                        "defaultShopperInteraction": "Ecommerce",
                        "status": "Active",
                        "shopWebAddress": "http:\/\/sample.store",
                        "merchantCity": "Amsterdam",
                        "primarySettlementCurrency": "EUR",
                        "dataCenters": [
                            {
                                "name": "default",
                                "livePrefix": ""
                            }
                        ],
                        "_links": {
                            "self": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest"
                            },
                            "apiCredentials": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest\/apiCredentials"
                            },
                            "users": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest\/users"
                            },
                            "webhooks": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest\/webhooks"
                            }
                        },
                        "description": "MagentoTest"
                    },
                    {
                        "id": "MagentoTest",
                        "name": "MagentoTest",
                        "companyId": "CompanyTest",
                        "captureDelay": "immediate",
                        "defaultShopperInteraction": "Ecommerce",
                        "status": "Active",
                        "shopWebAddress": "http:\/\/sample.store",
                        "merchantCity": "Amsterdam",
                        "primarySettlementCurrency": "EUR",
                        "dataCenters": [
                            {
                                "name": "default",
                                "livePrefix": ""
                            }
                        ],
                        "_links": {
                            "self": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest"
                            },
                            "apiCredentials": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest\/apiCredentials"
                            },
                            "users": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest\/users"
                            },
                            "webhooks": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/merchants\/MagentoTest\/webhooks"
                            }
                        },
                        "description": "MagentoTest"
                    }
                ]
            }
        JSON;

        $meResponseJson = <<<JSON
            {
                "id": "S2-123123123",
                "username": "ws_000000@Company.TestCompany",
                "description": "Test Credentials",
                "clientKey": "test_abcdefg",
                "allowedIpAddresses": [],
                "roles": [
                    "ROLE 01",
                    "ROLE 02"
                ],
                "active": true,
                "allowedOrigins": [
                    {
                        "id": "S2-123123123",
                        "domain": "http:\/\/192.168.58.10",
                        "_links": {
                            "self": {
                                "href": "https:\/\/management-test.adyen.com\/v1\/me\/allowedOrigins\/S2-123123123"
                            }
                        }
                    }
                ],
                "_links": {
                    "self": {
                        "href": "https:\/\/management-test.adyen.com\/v1\/me"
                    },
                    "allowedOrigins": {
                        "href": "https:\/\/management-test.adyen.com\/v1\/me\/allowedOrigins"
                    }
                },
                "companyName": "TestCompany"
            }
        JSON;

        $merchantAccountListResponse = new ListMerchantResponse(json_decode($merchantAccountListResponseJson, true));
        $meResponse = new MeApiCredential(json_decode($meResponseJson, true));

        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => 1
            ])
        ]);

        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class, [
                'getConfig' => $this->createConfiguredMock(HttpClientConfig::class, [
                    'get' => Environment::TEST
                ])
            ])
        ]);

        $managementHelper = $this->createManagementHelper($storeManagerMock, null, $dataHelperMock);
        $accountMerchantLevelApi = $this->createConfiguredMock(AccountMerchantLevelApi::class, [
            'listMerchantAccounts' => $merchantAccountListResponse
        ]);

        $myAPICredentialApi = $this->createConfiguredMock(MyAPICredentialApi::class, [
            'getApiCredentialDetails' => $meResponse
        ]);

        $result = $managementHelper->getMerchantAccountsAndClientKey($accountMerchantLevelApi, $myAPICredentialApi);

        $this->assertArrayHasKey('currentMerchantAccount', $result);
        $this->assertEquals('test_abcdefg', $result['clientKey']);
        $this->assertCount(2, $result['merchantAccounts']);
    }

    public function testSetupWebhookCredentialsSuccess()
    {
        $merchantId = 'MERCHANT_ID';
        $username = 'USERNAME';
        $password = 'PASSWORD';
        $url = 'https://www.test.store/webhook';
        $isDemoMode = true;
        $webhookId = null;

        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => 1
            ])
        ]);

        $encyptorMock = $this->createConfiguredMock(EncryptorInterface::class, [
            'encrypt' => 'ENCRYPTED_VALUE'
        ]);

        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class, [
                'getConfig' => $this->createConfiguredMock(HttpClientConfig::class, [
                    'get' => Environment::TEST
                ])
            ])
        ]);

        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getWebhookId' => $webhookId,
            'getMerchantAccount' => 'TestMerchantAccount'
        ]);

        $managementHelper = $this->createManagementHelper(
            $storeManagerMock,
            $encyptorMock,
            $dataHelperMock,
            $configHelperMock
        );


        $service = $this->createConfiguredMock(WebhooksMerchantLevelApi::class, [
            'setUpWebhook' => new Webhook(['id' => 'WH-0123456789']),
            'generateHmacKey' => new GenerateHmacKeyResponse(['hmacKey' => 'MOCK_HMAC_KEY'])
        ]);

        $result = $managementHelper->setupWebhookCredentials(
            $merchantId,
            $username,
            $password,
            $url,
            $isDemoMode,
            $service
        );

        $this->assertEquals('WH-0123456789', $result);
    }

    public function testSetupWebhookCredentialsWithStoredWebhookSuccess()
    {
        $merchantId = 'MERCHANT_ID';
        $username = 'USERNAME';
        $password = 'PASSWORD';
        $url = 'https://www.test.store/webhook';
        $isDemoMode = true;
        $webhookId = 'WH-000000000';

        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => 1
            ])
        ]);

        $encyptorMock = $this->createConfiguredMock(EncryptorInterface::class, [
            'encrypt' => 'ENCRYPTED_VALUE'
        ]);

        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class, [
                'getConfig' => $this->createConfiguredMock(HttpClientConfig::class, [
                    'get' => Environment::TEST
                ])
            ])
        ]);

        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getWebhookId' => $webhookId,
            'getMerchantAccount' => $merchantId
        ]);

        $adyenLogger = $this->createMock(AdyenLogger::class);
        $adyenLogger->expects($this->never())->method('error');
        $managementHelper = $this->createManagementHelper(
            $storeManagerMock,
            $encyptorMock,
            $dataHelperMock,
            $configHelperMock,
            $adyenLogger
        );

        $service = $this->createConfiguredMock(WebhooksMerchantLevelApi::class, [
            'updateWebhook' => new Webhook(['id' => 'WH-0123456789']),
            'generateHmacKey' => new GenerateHmacKeyResponse(['hmacKey' => 'MOCK_HMAC_KEY'])
        ]);

        $result = $managementHelper->setupWebhookCredentials(
            $merchantId,
            $username,
            $password,
            $url,
            $isDemoMode,
            $service
        );

        $this->assertEquals('WH-000000000', $result);
    }



    public function testSetupWebhookCredentialsWithFaildGenerateHmacKey()
    {
        $merchantId = 'MERCHANT_ID';
        $username = 'USERNAME';
        $password = 'PASSWORD';
        $url = 'https://www.test.store/webhook';
        $isDemoMode = false;
        $webhookId = 'WH-000000000';

        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => 1
            ])
        ]);

        $encyptorMock = $this->createConfiguredMock(EncryptorInterface::class, [
            'encrypt' => 'ENCRYPTED_VALUE'
        ]);

        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class, [
                'getConfig' => $this->createConfiguredMock(HttpClientConfig::class, [
                    'get' => Environment::TEST
                ])
            ])
        ]);

        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getWebhookId' => $webhookId,
            'getMerchantAccount' => $merchantId
        ]);

        $adyenLogger = $this->createMock(AdyenLogger::class);
        $managementHelper = $this->createManagementHelper(
            $storeManagerMock,
            $encyptorMock,
            $dataHelperMock,
            $configHelperMock,
            $adyenLogger
        );

        $service = $this->createConfiguredMock(WebhooksMerchantLevelApi::class, [
            'updateWebhook' => new Webhook(['id' => 'WH-0123456789'])
        ]);
        $service->expects($this->once())
            ->method('generateHmacKey')
            ->willThrowException(new \Exception('Some exception'));

        $this->expectException(\Exception::class);
        $result = $managementHelper->setupWebhookCredentials(
            $merchantId,
            $username,
            $password,
            $url,
            $isDemoMode,
            $service
        );
    }

    public function testSetupWebhookCredentialsFailLive(): void
    {
        $merchantId = 'TestMerchantAccount';
        $username = 'USERNAME';
        $password = 'PASSWORD';
        $url = 'https://www.test.store/webhook';
        $isDemoMode = false;
        $webhookId = null;

        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => 1
            ])
        ]);

        $encyptorMock = $this->createConfiguredMock(EncryptorInterface::class, [
            'encrypt' => 'ENCRYPTED_VALUE'
        ]);

        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class, [
                'getConfig' => $this->createConfiguredMock(HttpClientConfig::class, [
                    'get' => Environment::TEST
                ])
            ])
        ]);

        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getWebhookId' => $webhookId,
            'getMerchantAccount' => 'TestMerchantAccount'
        ]);

        $managementHelper = $this->createManagementHelper(
            $storeManagerMock,
            $encyptorMock,
            $dataHelperMock,
            $configHelperMock
        );

        $service = $this->createConfiguredMock(WebhooksMerchantLevelApi::class, []);
        $service->expects($this->any())->method('setUpWebhook')->willThrowException(new \Exception('Mock Service Exception'));
        $resultWebhookId = $managementHelper->setupWebhookCredentials(
            $merchantId,
            $username,
            $password,
            $url,
            $isDemoMode,
            $service
        );

        $this->assertEquals($webhookId, $resultWebhookId);
    }

    public function testGetAllowedOrigins()
    {
        $mockJsonResponse = <<<Json
            {
                "data": [
                    {
                        "id": "S2-000000",
                        "domain": "http://192.168.58.10",
                        "_links": {
                            "self": {
                                "href": "https://management-test.adyen.com/v1/me/allowedOrigins/S2-000000"
                            }
                        }
                    },
                    {
                        "id": "S2-000001",
                        "domain": "http://192.168.58.20",
                        "_links": {
                            "self": {
                                "href": "https://management-test.adyen.com/v1/me/allowedOrigins/S2-000001"
                            }
                        }
                    }
                ]
            }
        Json;

        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => 1
            ])
        ]);

        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class, [
                'getConfig' => $this->createConfiguredMock(HttpClientConfig::class, [
                    'get' => Environment::TEST
                ])
            ])
        ]);

        $managementHelper = $this->createManagementHelper(
            $storeManagerMock,
            null,
            $dataHelperMock
        );

        $myAPICredentialApi = $this->createConfiguredMock(MyAPICredentialApi::class, [
            'getAllowedOrigins' => new AllowedOriginsResponse(json_decode($mockJsonResponse, true))
        ]);

        $expectedArray = [
            'http://192.168.58.10',
            'http://192.168.58.20'
        ];

        $result = $managementHelper->getAllowedOrigins($myAPICredentialApi);

        $this->assertEquals($expectedArray, $result);
    }

    public function testWebhookTest()
    {
        $rawJsonResponse = <<<JSON
            {
                "data": [
                    {
                        "merchantId": "TEST_COMPANY",
                        "output": "[accepted]",
                        "requestSent": "SAMPLE_REQUESTS",
                        "responseCode": "200",
                        "responseTime": "160 ms",
                        "status": "success"
                    }
                ]
            }
        JSON;

        $webhookId = 'WH-000000000';
        $merchantId = 'MERCHANT_ID';

        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => 1
            ])
        ]);

        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class, [
                'getConfig' => $this->createConfiguredMock(HttpClientConfig::class, [
                    'get' => Environment::TEST
                ])
            ])
        ]);

        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getWebhookId' => $webhookId,
            'getMerchantAccount' => 'TestMerchantAccount'
        ]);

        $managementHelper = $this->createManagementHelper(
            $storeManagerMock,
            null,
            $dataHelperMock,
            $configHelperMock
        );


        $service = $this->createConfiguredMock(WebhooksMerchantLevelApi::class, [
            'testWebhook' => new TestWebhookResponse(json_decode($rawJsonResponse, true))
        ]);

        $result = $managementHelper->webhookTest($merchantId, $webhookId, $service);

        $success = isset($result['data']) &&
            in_array('success', array_column($result['data'], 'status'), true);

        $this->assertTrue($success);
    }

    /**
     * @param StoreManager|null $storeManager
     * @param EncryptorInterface|null $encryptor
     * @param Data|null $dataHelper
     * @param Config|null $configHelper
     * @param AdyenLogger|null $adyenLogger
     * @param ManagerInterface|null $messageManager
     * @return ManagementHelper
     */
    private function createManagementHelper(
        StoreManager $storeManager = null,
        EncryptorInterface $encryptor = null,
        Data $dataHelper = null,
        Config $configHelper = null,
        AdyenLogger $adyenLogger = null,
        ManagerInterface $messageManager = null
    ): ManagementHelper {

        if (is_null($storeManager)) {
            $storeManager = $this->createMock(StoreManager::class);
        }

        if (is_null($encryptor)) {
            $encryptor = $this->createMock(EncryptorInterface::class);
        }

        if (is_null($dataHelper)) {
            $dataHelper = $this->createMock(Data::class);
        }

        if (is_null($configHelper)) {
            $configHelper = $this->createMock(Config::class);
        }

        if (is_null($adyenLogger)) {
            $adyenLogger = $this->createMock(AdyenLogger::class);
        }

        if (is_null($messageManager)) {
            $messageManager = $this->createMock(ManagerInterface::class);
        }

        return new ManagementHelper(
            $storeManager,
            $encryptor,
            $dataHelper,
            $configHelper,
            $adyenLogger,
            $messageManager
        );
    }

    public function testSaveAllowedOrigin()
    {
        $helper = $this->createManagementHelper();
        $service = $this->createMock(MyAPICredentialApi::class);
        $domian = 'DOMAIN';
        $service->expects($this->once())->method('addAllowedOrigin')->willReturn(new AllowedOrigin());
        $helper->saveAllowedOrigin($service, $domian);
    }

    public function testWebhookTestFailure()
    {
        $webhookId = 'WH-000000000';
        $merchantId = 'MERCHANT_ID';
        $adyenLogger = $this->createMock(AdyenLogger::class);
        $helper = $this->createManagementHelper(null,null,null,null,$adyenLogger);
        $service = $this->createMock(WebhooksMerchantLevelApi::class);
        $service->expects($this->once())->method('testWebhook')->willThrowException(new AdyenException());
        $adyenLogger->expects($this->never())->method('addAdyenInfoLog');
        $adyenLogger->expects($this->once())->method('error');
        $helper->webhookTest($webhookId, $merchantId, $service);
    }

    public function testGetAdyenApiClient()
    {
        $storeId = 1;
        $apiKey = 'API_KEY';
        $storeManagerMock = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, [
                'getId' => $storeId
            ])
        ]);
        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getApiKey' => $apiKey
        ]);
        $dataHelperMock = $this->createConfiguredMock(Data::class, [
            'initializeAdyenClient' => $this->createConfiguredMock(Client::class,[])
        ]);
        $helper = $this->createManagementHelper($storeManagerMock,null,$dataHelperMock, $configHelperMock);
        $dataHelperMock
            ->expects($this->once())
            ->method('initializeAdyenClient')
        ->with($storeId, $apiKey);
        $helper->getAdyenApiClient($apiKey, false);
    }

    public function testGetAccountMerchantLevelApi()
    {
        $service = $this->createManagementHelper()->getAccountMerchantLevelApi($this->clientMock);
        $this->assertInstanceOf(AccountMerchantLevelApi::class, $service);
    }

    public function testGetMyAPICredentialApi()
    {
        $service = $this->createManagementHelper()->getMyAPICredentialApi($this->clientMock);
        $this->assertInstanceOf(MyAPICredentialApi::class, $service);
    }

    public function testWebhooksMerchantLevelApi()
    {
        $service = $this->createManagementHelper()->getWebhooksMerchantLevelApi($this->clientMock);
        $this->assertInstanceOf(WebhooksMerchantLevelApi::class, $service);
    }
}
