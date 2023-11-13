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

use Adyen\Client;
use Adyen\Config as HttpClientConfig;
use Adyen\Environment;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\ManagementHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\ResourceModel\Management\AllowedOrigins;
use Adyen\Service\ResourceModel\Management\Me;
use Adyen\Service\ResourceModel\Management\MerchantAccount;
use Adyen\Service\ResourceModel\Management\MerchantWebhooks;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManager;

class ManagementHelperTest extends AbstractAdyenTestCase
{
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

        $merchantAccountListResponse = json_decode($merchantAccountListResponseJson, true);
        $meResponse = json_decode($meResponseJson, true);

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
        $managementApiService = $managementHelper->getManagementApiService("APIKEY", true);

        $managementApiService->merchantAccount = $this->createConfiguredMock(MerchantAccount::class, [
            'list' => $merchantAccountListResponse
        ]);

        $managementApiService->me = $this->createConfiguredMock(Me::class, [
            'retrieve' => $meResponse
        ]);

        $result = $managementHelper->getMerchantAccountsAndClientKey($managementApiService);

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

        $managementApiService = $managementHelper->getManagementApiService("APIKEY", true);
        $managementApiService->merchantWebhooks = $this->createConfiguredMock(MerchantWebhooks::class, [
            'generateHmac' => [
                'hmacKey' => "MOCK_HMAC_KEY"
            ],
            'create' => [
                'id' => 'WH-0123456789'
            ]
        ]);

        $result = $managementHelper->setupWebhookCredentials(
            $merchantId,
            $username,
            $password,
            $url,
            $isDemoMode,
            $managementApiService
        );

        $this->assertEquals('WH-0123456789', $result);
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

        $managementApiService = $managementHelper->getManagementApiService("APIKEY", true);
        $managementApiService->merchantWebhooks = $this->createConfiguredMock(MerchantWebhooks::class, [
            'generateHmac' => [
                'hmacKey' => "MOCK_HMAC_KEY"
            ],
            'create' => $this->throwException(new \Exception('Mock Service Exception'))
        ]);

        $resultWebhookId = $managementHelper->setupWebhookCredentials(
            $merchantId,
            $username,
            $password,
            $url,
            $isDemoMode,
            $managementApiService
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
        $managementApiService = $managementHelper->getManagementApiService("APIKEY", true);

        $managementApiService->allowedOrigins = $this->createConfiguredMock(AllowedOrigins::class, [
            'list' => json_decode($mockJsonResponse, true)
        ]);

        $expectedArray = [
            'http://192.168.58.10',
            'http://192.168.58.20'
        ];

        $result = $managementHelper->getAllowedOrigins($managementApiService);

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

        $managementApiService = $managementHelper->getManagementApiService("APIKEY", true);
        $managementApiService->merchantWebhooks = $this->createConfiguredMock(MerchantWebhooks::class, [
            'test' => json_decode($rawJsonResponse, true)
        ]);

        $result = $managementHelper->webhookTest($merchantId, $webhookId, $managementApiService);

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
}
