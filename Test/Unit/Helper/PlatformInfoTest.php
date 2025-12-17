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
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
use Magento\Payment\Model\InfoInterface;

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

    public function testBuildRequestHeadersWithFrontendTypeInPayment(): void
    {
        $this->productMetadata->method('getName')->willReturn('Magento');
        $this->productMetadata->method('getVersion')->willReturn('2.4.7');
        $this->productMetadata->method('getEdition')->willReturn('Community');

        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getAdditionalInformation')
            ->with(HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY)
            ->willReturn('web');

        $this->componentRegistrar->method('getPath')
            ->with(\Magento\Framework\Component\ComponentRegistrar::MODULE, 'Adyen_Payment')
            ->willReturn(__DIR__ . '/../../../'); // or mock to valid path if you want to test version logic

        // Mock the getModuleVersion result
        $platformInfo = $this->getMockBuilder(PlatformInfo::class)
            ->setConstructorArgs([$this->componentRegistrar, $this->productMetadata, $this->request])
            ->onlyMethods(['getModuleVersion'])
            ->getMock();

        $platformInfo->method('getModuleVersion')->willReturn('9.6.0');

        $headers = $platformInfo->buildRequestHeaders($payment);

        $this->assertSame('Magento', $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_NAME]);
        $this->assertSame('2.4.7', $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_VERSION]);
        $this->assertSame('Community', $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_EDITION]);
        $this->assertSame('adyen-magento2', $headers[HeaderDataBuilderInterface::MERCHANT_APPLICATION_NAME]);
        $this->assertSame('9.6.0', $headers[HeaderDataBuilderInterface::MERCHANT_APPLICATION_VERSION]);
        $this->assertSame('web', $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE]);
    }

    public function testBuildRequestHeadersWithFallbackToRequestPath(): void
    {
        $this->productMetadata->method('getName')->willReturn('Magento');
        $this->productMetadata->method('getVersion')->willReturn('2.4.7');
        $this->productMetadata->method('getEdition')->willReturn('Community');

        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getAdditionalInformation')
            ->with(HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY)
            ->willReturn(null);

        $this->request->method('getOriginalPathInfo')->willReturn('/graphql');
        $this->request->method('getMethod')->willReturn('POST');

        $this->componentRegistrar->method('getPath')
            ->with(\Magento\Framework\Component\ComponentRegistrar::MODULE, 'Adyen_Payment')
            ->willReturn(__DIR__ . '/../../../');

        $platformInfo = $this->getMockBuilder(PlatformInfo::class)
            ->setConstructorArgs([$this->componentRegistrar, $this->productMetadata, $this->request])
            ->onlyMethods(['getModuleVersion'])
            ->getMock();

        $platformInfo->method('getModuleVersion')->willReturn('9.6.0');

        $headers = $platformInfo->buildRequestHeaders($payment);

        $this->assertSame('headless-graphql', $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE]);
    }

    public function testGetModuleVersionWithValidComposerJson(): void
    {
        $tempDir = sys_get_temp_dir() . '/adyen_module_test_' . uniqid();
        mkdir($tempDir);

        $composerJsonContent = json_encode(['version' => '9.7.0']);
        file_put_contents($tempDir . '/composer.json', $composerJsonContent);

        $this->componentRegistrar->method('getPath')
            ->with(\Magento\Framework\Component\ComponentRegistrar::MODULE, 'Adyen_Payment')
            ->willReturn($tempDir);

        $version = $this->platformInfo->getModuleVersion();

        $this->assertSame('9.7.0', $version);

        // Clean up
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function testGetModuleVersionWithMissingVersion(): void
    {
        $tempDir = sys_get_temp_dir() . '/adyen_module_test_' . uniqid();
        mkdir($tempDir);

        $composerJsonContent = json_encode(['name' => 'adyen/magento2']);
        file_put_contents($tempDir . '/composer.json', $composerJsonContent);

        $this->componentRegistrar->method('getPath')
            ->with(\Magento\Framework\Component\ComponentRegistrar::MODULE, 'Adyen_Payment')
            ->willReturn($tempDir);

        $version = $this->platformInfo->getModuleVersion();

        $this->assertSame('Version is not available in composer.json', $version);

        // Clean up
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

}
