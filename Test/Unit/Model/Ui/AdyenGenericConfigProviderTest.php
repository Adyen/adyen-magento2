<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Locale;

class AdyenGenericConfigProviderTest extends AbstractAdyenTestCase
{
    protected AdyenGenericConfigProvider $provider;
    protected Data|MockObject $adyenHelperMock;
    protected Config|MockObject $adyenConfigHelperMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected RequestInterface|MockObject $requestMock;
    protected UrlInterface|MockObject $urlMock;
    protected AgreementsConfigProvider|MockObject $agreementsConfigProviderMock;
    protected CspNonceProvider|MockObject $cspNonceProviderMock;
    protected array $txVariants = [];
    protected array $customMethodRenderers = [];
    protected PaymentMethods $paymentMethodsMock;
    protected Locale $localeMock;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenConfigHelperMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->urlMock = $this->createMock(UrlInterface::class);
        $this->agreementsConfigProviderMock = $this->createMock(AgreementsConfigProvider::class);
        $this->cspNonceProviderMock = $this->createMock(CspNonceProvider::class);
        $this->localeMock = $this->createMock(Locale::class);
        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);

        $this->provider = new AdyenGenericConfigProvider(
            $this->adyenHelperMock,
            $this->adyenConfigHelperMock,
            $this->storeManagerMock,
            $this->requestMock,
            $this->urlMock,
            $this->agreementsConfigProviderMock,
            $this->cspNonceProviderMock,
            $this->localeMock,
            $this->paymentMethodsMock,
            $this->txVariants,
            $this->customMethodRenderers
        );
    }

    public function testGetConfig(): void
    {
        $storeId = 1;
        $clientKeyMock = 'CLIENT_KEY';
        $merchantAccountMock = 'MERCHANT_ACCOUNT';
        $isDemo = true;
        $environment = 'test';
        $nonceMock = 'NONCE';
        $checkoutEnvironment = 'test';
        $storeLocale = 'nl_NL';
        $chargedCurrency = 'display';
        $hasHolderName = true;
        $holderNameRequired = true;

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->expects($this->once())
            ->method('getId')
            ->willReturn($storeId);

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willReturn($storeMock);

        $this->adyenConfigHelperMock->expects($this->once())
            ->method('isDemoMode')
            ->willReturn($isDemo);

        $this->adyenConfigHelperMock->expects($this->once())
            ->method('getClientKey')
            ->with($environment)
            ->willReturn($clientKeyMock);

        $this->adyenConfigHelperMock->expects($this->once())
            ->method('getMerchantAccount')
            ->with($storeId)
            ->willReturn($merchantAccountMock);

        $this->adyenHelperMock->expects($this->once())
            ->method('getCheckoutEnvironment')
            ->with($storeId)
            ->willReturn($checkoutEnvironment);

        $this->localeMock->expects($this->once())
            ->method('getStoreLocale')
            ->with($storeId)
            ->willReturn($storeLocale);

        $this->adyenConfigHelperMock->expects($this->once())
            ->method('getChargedCurrency')
            ->with($storeId)
            ->willReturn($chargedCurrency);

        $this->adyenConfigHelperMock->expects($this->once())
            ->method('getHasHolderName')
            ->with($storeId)
            ->willReturn($hasHolderName);

        $this->adyenConfigHelperMock->expects($this->once())
            ->method('getHolderNameRequired')
            ->with($storeId)
            ->willReturn($holderNameRequired);

        $this->cspNonceProviderMock->expects($this->once())
            ->method('generateNonce')
            ->willReturn($nonceMock);


        $config = $this->provider->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('payment', $config);
        $this->assertArrayHasKey('adyen', $config['payment']);
        $this->assertArrayHasKey('clientKey', $config['payment']['adyen']);
        $this->assertArrayHasKey('merchantAccount', $config['payment']['adyen']);
        $this->assertArrayHasKey('checkoutEnvironment', $config['payment']['adyen']);
        $this->assertArrayHasKey('locale', $config['payment']['adyen']);
        $this->assertArrayHasKey('chargedCurrency', $config['payment']['adyen']);
        $this->assertArrayHasKey('hasHolderName', $config['payment']['adyen']);
        $this->assertArrayHasKey('holderNameRequired', $config['payment']['adyen']);
        $this->assertArrayHasKey('cspNonce', $config['payment']['adyen']);

        $this->assertEquals($clientKeyMock, $config['payment']['adyen']['clientKey']);
        $this->assertEquals($merchantAccountMock, $config['payment']['adyen']['merchantAccount']);
        $this->assertEquals($checkoutEnvironment, $config['payment']['adyen']['checkoutEnvironment']);
        $this->assertEquals($storeLocale, $config['payment']['adyen']['locale']);
        $this->assertEquals($chargedCurrency, $config['payment']['adyen']['chargedCurrency']);
        $this->assertEquals($hasHolderName, $config['payment']['adyen']['hasHolderName']);
        $this->assertEquals($holderNameRequired, $config['payment']['adyen']['holderNameRequired']);
        $this->assertEquals($nonceMock, $config['payment']['adyen']['cspNonce']);
    }
}
