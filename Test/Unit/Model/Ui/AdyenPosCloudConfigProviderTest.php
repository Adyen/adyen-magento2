<?php

namespace Adyen\Payment\Test\Unit\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManager;

class AdyenPosCloudConfigProviderTest extends AbstractAdyenTestCase
{
    private $adyenPosCloudConfigProvider;

    private $requestMock;
    private $urlBuilderMock;
    private $connectedTerminalsHelperMock;
    private $serializerMock;
    private $configHelperMock;
    private $storeManagerMock;

    const STORE_ID = 1;

    public function setUp(): void
    {
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);

        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->connectedTerminalsHelperMock = $this->createMock(ConnectedTerminals::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManager::class);

        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->adyenPosCloudConfigProvider = new AdyenPosCloudConfigProvider(
            $this->requestMock,
            $this->urlBuilderMock,
            $this->connectedTerminalsHelperMock,
            $this->serializerMock,
            $this->configHelperMock,
            $this->storeManagerMock
        );
    }

    public function testGetConfigWithoutInstallments()
    {
        $successPageUrl = '/onepage/success';
        $isActive = true;

        $this->urlBuilderMock->expects($this->once())->method('getUrl')->willReturn($successPageUrl);

        $this->configHelperMock->expects($this->once())
            ->method('getAdyenPosCloudPaymentAction')
            ->with(self::STORE_ID)
            ->willReturn(MethodInterface::ACTION_ORDER);
        $this->configHelperMock->expects($this->any())
            ->method('getAdyenPosCloudConfigData')
            ->will($this->returnValueMap([
                ['active', self::STORE_ID, true, $isActive],
                ['enable_installments', self::STORE_ID, false, false],
                ['installments', self::STORE_ID, false, []]
            ]));

        $config = $this->adyenPosCloudConfigProvider->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('payment', $config);
        $this->assertTrue($config['payment']['adyen_pos_cloud']['isActive']);
    }
}
