<?php
declare(strict_types=1);

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Source;
use Magento\Payment\Model\CcConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class AdyenCcConfigProviderTest extends AbstractAdyenTestCase
{
    private ?AdyenCcConfigProvider $adyenCcConfigProvider = null;
    private Data&MockObject $adyenHelperMock;
    private RequestInterface&MockObject $requestMock;
    private UrlInterface&MockObject $urlBuilderMock;
    private Source&MockObject $assetSourceMock;
    private StoreManagerInterface&MockObject $storeManagerMock;
    private CcConfig&MockObject $ccConfigMock;
    private SerializerInterface&MockObject $serializerMock;
    private Config&MockObject $configHelperMock;
    private PaymentMethods&MockObject $paymentMethodsHelperMock;
    private Vault&MockObject $vaultHelperMock;
    private Http&MockObject $requestHttpMock;
    private Locale&MockObject $localeHelper;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->requestHttpMock = $this->createMock(Http::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->assetSourceMock = $this->createMock(Source::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->ccConfigMock = $this->createMock(CcConfig::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->vaultHelperMock = $this->createMock(Vault::class);
        $this->localeHelper = $this->createMock(Locale::class);

        $this->adyenCcConfigProvider = new AdyenCcConfigProvider(
            $this->adyenHelperMock,
            $this->requestMock,
            $this->urlBuilderMock,
            $this->assetSourceMock,
            $this->storeManagerMock,
            $this->ccConfigMock,
            $this->serializerMock,
            $this->configHelperMock,
            $this->paymentMethodsHelperMock,
            $this->vaultHelperMock,
            $this->requestHttpMock,
            $this->localeHelper
        );
    }

    protected function tearDown(): void
    {
        $this->adyenCcConfigProvider = null;
    }

    #[DataProvider('provideGetConfigTestData')]
    public function testGetConfig(bool $enableInstallments): void
    {
        $storeId = PHP_INT_MAX;
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManagerMock->method('getStore')->willReturn($store);

        $controllerName = 'index';

        $this->configHelperMock->method('getAdyenCcConfigData')->willReturnMap([
            ['enable_installments', null, $enableInstallments],
            ['installments', null, 'mock_serialized_installments'],
            ['useccv', null, true]
        ]);

        $this->adyenHelperMock->method('getAdyenCcTypes')
            ->willReturn(['MC' => ['name' => 'MasterCard', 'code_alt' => 'mc']]);

        $assetMock = $this->createMock(File::class);
        $assetMock->method('getSourceFile')->willReturn(__DIR__ . '/../../../../view/base/web/images/adyen/adyen-hq.svg');

        $this->ccConfigMock->method('createAsset')->willReturn($assetMock);
        $this->assetSourceMock->method('findSource')->willReturn('mock_relative_icon_path');

        $this->ccConfigMock->expects($this->once())->method('getCcMonths');
        $this->ccConfigMock->expects($this->once())->method('getCcYears');
        $this->ccConfigMock->expects($this->once())->method('getCvvImageUrl');
        $this->requestHttpMock->method('getControllerName')->willReturn($controllerName);

        $config = $this->adyenCcConfigProvider->getConfig();

        $adyenCc = $config['payment']['adyenCc'];
        $ccform = $config['payment']['ccform'];

        $this->assertArrayHasKey('installments', $adyenCc);
        $this->assertArrayHasKey('isClickToPayEnabled', $adyenCc);
        $this->assertArrayHasKey('controllerName', $adyenCc);
        $this->assertArrayHasKey('icons', $adyenCc);
        $this->assertArrayHasKey('isCardRecurringEnabled', $adyenCc);
        $this->assertArrayHasKey('locale', $adyenCc);
        $this->assertArrayHasKey('title', $adyenCc);
        $this->assertArrayHasKey('methodCode', $adyenCc);

        $this->assertArrayHasKey('availableTypes', $ccform);
        $this->assertArrayHasKey('availableTypesByAlt', $ccform);
        $this->assertArrayHasKey('months', $ccform);
        $this->assertArrayHasKey('years', $ccform);
        $this->assertArrayHasKey('hasVerification', $ccform);
        $this->assertArrayHasKey('cvvImageUrl', $ccform);
    }

    public static function provideGetConfigTestData(): array
    {
        return [
            'installments enabled' => [true],
            'installments disabled' => [false],
        ];
    }

    public function testConstants(): void
    {
        $this->assertSame('adyen_cc', AdyenCcConfigProvider::CODE);
        $this->assertSame('adyen_cc_vault', AdyenCcConfigProvider::CC_VAULT_CODE);
    }
}
