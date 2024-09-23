<?php

namespace Adyen\Payment\Test\Unit\Controller\Adminhtml\Configuration;

use Adyen\Client;
use Adyen\Model\Management\TestWebhookResponse;
use Adyen\Payment\Controller\Adminhtml\Configuration\WebhookTest;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ManagementHelper;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Management\WebhooksMerchantLevelApi;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class WebhookTestTest extends AbstractAdyenTestCase
{
    private $contextMock;
    private $managementHelper;
    private $resultJsonFactoryMock;
    private $storeManagerMock;
    private $configHelperMock;
    private WebhookTest $webhookTestController;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->managementHelper = $this->createMock(ManagementHelper::class);
        $this->resultJsonFactoryMock = $this->createMock(JsonFactory::class);
        $this->storeManagerMock = $this->createMock(StoreManager::class);
        $this->configHelperMock = $this->createMock(Config::class);

        $objectManager = new ObjectManager($this);
        $this->webhookTestController = $objectManager->getObject(
            WebhookTest::class,
            [
                'context' => $this->contextMock,
                'managementApiHelper' => $this->managementHelper,
                'resultJsonFactory' => $this->resultJsonFactoryMock,
                'storeManager' => $this->storeManagerMock,
                'configHelper' => $this->configHelperMock
            ]
        );
    }

    public function testExecuteSuccess()
    {
        // Arrange
        $storeId = 1;
        $merchantAccount = 'TestMerchantAccount';
        $webhookId = 'TestWebhookId';
        $isDemoMode = true;
        $apiKey = 'TestApiKey';
        $response = ['success' => true];

        $store = $this->createMock(Store::class);
        $this->storeManagerMock->method('getStore')->willReturn($store);
        $this->configHelperMock->method('getMerchantAccount')->willReturn($merchantAccount);
        $this->configHelperMock->method('getWebhookId')->willReturn($webhookId);
        $this->configHelperMock->method('isDemoMode')->willReturn($isDemoMode);
        $this->configHelperMock->method('getApiKey')->willReturn($apiKey);
        $client = $this->createMock(Client::class);
        $service = $this->createConfiguredMock(WebhooksMerchantLevelApi::class, [
            'testWebhook' => new TestWebhookResponse(['status'=>'success'])
        ]);
        $this->managementHelper->method('getAdyenApiClient')->willReturn($client);
        $this->managementHelper->method('getWebhooksMerchantLevelApi')->willReturn($service);
        $resultJsonMock = $this->createMock(\Magento\Framework\Controller\Result\Json::class);
        $resultJsonMock->expects($this->once())->method('setData')->willReturn($resultJsonMock);
        $this->resultJsonFactoryMock->method('create')->willReturn($resultJsonMock);
        // Act
        $result = $this->webhookTestController->execute();
        // Assert
        $this->assertSame($resultJsonMock, $result);
    }
}
