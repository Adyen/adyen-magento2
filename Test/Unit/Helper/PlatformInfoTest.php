<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Client;
use Adyen\Config;
use Adyen\Model\Checkout\ApplicationInfo;
use Adyen\Model\Checkout\CommonField;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Component\ComponentRegistrarInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PlatformInfo::class)]
class PlatformInfoTest extends AbstractAdyenTestCase
{
    private ComponentRegistrarInterface $componentRegistrar;
    private ProductMetadataInterface $productMetadata;
    private Http $request;
    private PlatformInfo $platformInfo;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);
        $this->request = $this->createMock(Http::class);

        $this->platformInfo = new PlatformInfo(
            $this->componentRegistrar,
            $this->productMetadata,
            $this->request
        );
    }

    public function testGetModuleName(): void
    {
        $this->assertSame('adyen-magento2', $this->platformInfo->getModuleName());
    }

    public function testPadShopperReference(): void
    {
        $this->assertSame('001', $this->platformInfo->padShopperReference('1'));
        $this->assertSame('010', $this->platformInfo->padShopperReference('10'));
        $this->assertSame('100', $this->platformInfo->padShopperReference('100'));
        $this->assertSame('1000', $this->platformInfo->padShopperReference('1000'));
    }

    public function testGetMagentoDetails(): void
    {
        $this->productMetadata->method('getName')->willReturn('Magento');
        $this->productMetadata->method('getVersion')->willReturn('2.4.7');
        $this->productMetadata->method('getEdition')->willReturn('Community');

        $details = $this->platformInfo->getMagentoDetails();

        $this->assertSame('Magento', $details['name']);
        $this->assertSame('2.4.7', $details['version']);
        $this->assertSame('Community', $details['edition']);
    }

    public function testBuildApplicationInfo(): void
    {
        $client = $this->createMock(Client::class);
        $config = $this->createMock(Config::class);

        $client->method('getLibraryName')->willReturn('adyen-php-api-library');
        $client->method('getLibraryVersion')->willReturn('10.0.0');
        $client->method('getConfig')->willReturn($config);

        $config->method('getAdyenPaymentSource')->willReturn(['name' => 'Magento', 'version' => '2.4']);
        $config->method('getExternalPlatform')->willReturn(null);
        $config->method('getMerchantApplication')->willReturn(null);

        $applicationInfo = $this->platformInfo->buildApplicationInfo($client);

        $this->assertInstanceOf(ApplicationInfo::class, $applicationInfo);
        $this->assertInstanceOf(CommonField::class, $applicationInfo->getAdyenLibrary());
        $this->assertInstanceOf(CommonField::class, $applicationInfo->getAdyenPaymentSource());
    }
}
