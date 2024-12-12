<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Source;
use Magento\Payment\Model\CcConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenCcConfigProviderTest extends AbstractAdyenTestCase
{
    protected ?AdyenCcConfigProvider $adyenCcConfigProvider;
    protected Data|MockObject $adyenHelperMock;
    protected RequestInterface|MockObject $requestMock;
    protected UrlInterface|MockObject $urlBuilderMock;
    protected Source|MockObject $assetSourceMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected CcConfig|MockObject $ccConfigMock;
    protected SerializerInterface|MockObject $serializerMock;
    protected Config|MockObject $configHelperMock;
    protected PaymentMethods|MockObject $paymentMethodsHelperMock;
    protected Vault|MockObject $vaultHelperMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->assetSourceMock = $this->createMock(Source::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->ccConfigMock = $this->createMock(CcConfig::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->vaultHelperMock = $this->createMock(Vault::class);

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
            $this->vaultHelperMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->adyenCcConfigProvider = null;
    }

    /**
     * @param $enableInstallments
     * @return void
     *
     * @dataProvider getConfigTestDataProvider
     */
    public function testGetConfig($enableInstallments)
    {
        $storeId = PHP_INT_MAX;
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManagerMock->method('getStore')->willReturn($store);

        $this->configHelperMock->method('getAdyenCcConfigData')
            ->willReturnMap([
                ['enable_installments', null, $enableInstallments],
                ['installments', null, 'mock_serialized_installments'],
                ['useccv', null, true]
        ]);

        $this->adyenHelperMock->method('getAdyenCcTypes')
            ->willReturn(['MC' => ['name' => 'MasterCard', 'code_alt' => 'mc']]);

        $assetMockMasterCard = $this->createMock(File::class);
        $assetMockMasterCard->method('getSourceFile')->willReturn(
            __DIR__ . '/../../../../view/base/web/images/adyen/adyen-hq.svg'
        );

        $this->ccConfigMock->method('createAsset')->willReturn($assetMockMasterCard);

        $this->assetSourceMock->method('findSource')
            ->with($assetMockMasterCard)
            ->willReturn('mock_relative_icon_path');

        $this->ccConfigMock->expects($this->once())
            ->method('getCcMonths');

        $this->ccConfigMock->expects($this->once())
            ->method('getCcYears');

        $this->ccConfigMock->expects($this->once())
            ->method('getCvvImageUrl');

        $configObject = $this->adyenCcConfigProvider->getConfig();

        $this->assertArrayHasKey('installments', $configObject['payment']['adyenCc']);
        $this->assertArrayHasKey('isClickToPayEnabled', $configObject['payment']['adyenCc']);
        $this->assertArrayHasKey('icons', $configObject['payment']['adyenCc']);
        $this->assertArrayHasKey('isCardRecurringEnabled', $configObject['payment']['adyenCc']);
        $this->assertArrayHasKey('locale', $configObject['payment']['adyenCc']);
        $this->assertArrayHasKey('title', $configObject['payment']['adyenCc']);
        $this->assertArrayHasKey('methodCode', $configObject['payment']['adyenCc']);
        $this->assertArrayHasKey('availableTypes', $configObject['payment']['ccform']);
        $this->assertArrayHasKey('availableTypesByAlt', $configObject['payment']['ccform']);
        $this->assertArrayHasKey('months', $configObject['payment']['ccform']);
        $this->assertArrayHasKey('years', $configObject['payment']['ccform']);
        $this->assertArrayHasKey('hasVerification', $configObject['payment']['ccform']);
        $this->assertArrayHasKey('cvvImageUrl', $configObject['payment']['ccform']);
    }

    /**
     * @return array
     */
    protected function getConfigTestDataProvider(): array
    {
        return [
            ['enableInstallments' => true],
            ['enableInstallments' => false]
        ];
    }

    /**
     * @return void
     */
    public function testConstants()
    {
        $this->assertEquals('adyen_cc', AdyenCcConfigProvider::CODE);
        $this->assertEquals('adyen_cc_vault', AdyenCcConfigProvider::CC_VAULT_CODE);
    }
}
