<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Ui\AdyenVirtualQuoteConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(AdyenVirtualQuoteConfigProvider::class)]
class AdyenVirtualQuoteConfigProviderTest extends AbstractAdyenTestCase
{
    private AdyenVirtualQuoteConfigProvider $adyenVirtualQuoteConfigProvider;

    private MockObject $paymentMethodsHelperMock;
    private MockObject $storeManagerMock;
    private MockObject $configHelperMock;
    private MockObject $sessionMock;
    private MockObject $connectedTerminalsHelperMock;
    private MockObject $quoteMock;
    private MockObject $storeMock;

    private const STORE_ID = 1;

    protected function setUp(): void
    {
        $this->storeMock = $this->createMock(StoreInterface::class);
        $this->storeMock->method('getId')->willReturn(self::STORE_ID);

        $this->quoteMock = $this->createMock(Quote::class);

        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->sessionMock = $this->createMock(Session::class);
        $this->connectedTerminalsHelperMock = $this->createMock(ConnectedTerminals::class);

        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->sessionMock->method('getQuote')->willReturn($this->quoteMock);

        $this->adyenVirtualQuoteConfigProvider = new AdyenVirtualQuoteConfigProvider(
            $this->paymentMethodsHelperMock,
            $this->storeManagerMock,
            $this->configHelperMock,
            $this->sessionMock,
            $this->connectedTerminalsHelperMock
        );
    }

    #[Test]
    public function testGetConfigReturnsEmptyArrayForNonVirtualQuote(): void
    {
        $this->quoteMock->method('isVirtual')->willReturn(false);

        $this->paymentMethodsHelperMock->expects($this->never())->method('getApiResponse');
        $this->connectedTerminalsHelperMock->expects($this->never())->method('getConnectedTerminals');

        $config = $this->adyenVirtualQuoteConfigProvider->getConfig();

        $this->assertSame([], $config);
    }

    #[Test]
    public function testGetConfigReturnsPaymentMethodsForVirtualQuoteWhenActive(): void
    {
        $paymentMethodsResponse = '{"paymentMethods":[{"type":"scheme"}]}';

        $this->quoteMock->method('isVirtual')->willReturn(true);

        $this->configHelperMock->method('getIsPaymentMethodsActive')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $this->configHelperMock->method('getAdyenPosCloudConfigData')
            ->with('active', self::STORE_ID, true)
            ->willReturn(false);

        $this->paymentMethodsHelperMock->expects($this->once())
            ->method('getApiResponse')
            ->with($this->quoteMock)
            ->willReturn($paymentMethodsResponse);

        $this->connectedTerminalsHelperMock->expects($this->never())
            ->method('getConnectedTerminals');

        $config = $this->adyenVirtualQuoteConfigProvider->getConfig();

        $this->assertArrayHasKey('payment', $config);
        $this->assertArrayHasKey('adyen', $config['payment']);
        $this->assertArrayHasKey('virtualQuote', $config['payment']['adyen']);
        $this->assertSame(
            $paymentMethodsResponse,
            $config['payment']['adyen']['virtualQuote']['paymentMethodsResponse']
        );
        $this->assertArrayNotHasKey('connectedTerminals', $config['payment']['adyen']['virtualQuote']);
    }

    #[Test]
    public function testGetConfigReturnsConnectedTerminalsForVirtualQuoteWhenPosCloudActive(): void
    {
        $connectedTerminalsResponse = ['uniqueTerminalIds' => ['terminal1', 'terminal2']];

        $this->quoteMock->method('isVirtual')->willReturn(true);

        $this->configHelperMock->method('getIsPaymentMethodsActive')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $this->configHelperMock->method('getAdyenPosCloudConfigData')
            ->with('active', self::STORE_ID, true)
            ->willReturn(true);

        $this->paymentMethodsHelperMock->expects($this->never())
            ->method('getApiResponse');

        $this->connectedTerminalsHelperMock->expects($this->once())
            ->method('getConnectedTerminals')
            ->with(self::STORE_ID)
            ->willReturn($connectedTerminalsResponse);

        $config = $this->adyenVirtualQuoteConfigProvider->getConfig();

        $this->assertArrayHasKey('payment', $config);
        $this->assertArrayHasKey('adyen', $config['payment']);
        $this->assertArrayHasKey('virtualQuote', $config['payment']['adyen']);
        $this->assertSame(
            $connectedTerminalsResponse,
            $config['payment']['adyen']['virtualQuote']['connectedTerminals']
        );
        $this->assertArrayNotHasKey('paymentMethodsResponse', $config['payment']['adyen']['virtualQuote']);
    }

    #[Test]
    public function testGetConfigReturnsBothPaymentMethodsAndConnectedTerminalsWhenBothActive(): void
    {
        $paymentMethodsResponse = '{"paymentMethods":[{"type":"scheme"}]}';
        $connectedTerminalsResponse = ['uniqueTerminalIds' => ['terminal1', 'terminal2']];

        $this->quoteMock->method('isVirtual')->willReturn(true);

        $this->configHelperMock->method('getIsPaymentMethodsActive')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $this->configHelperMock->method('getAdyenPosCloudConfigData')
            ->with('active', self::STORE_ID, true)
            ->willReturn(true);

        $this->paymentMethodsHelperMock->expects($this->once())
            ->method('getApiResponse')
            ->with($this->quoteMock)
            ->willReturn($paymentMethodsResponse);

        $this->connectedTerminalsHelperMock->expects($this->once())
            ->method('getConnectedTerminals')
            ->with(self::STORE_ID)
            ->willReturn($connectedTerminalsResponse);

        $config = $this->adyenVirtualQuoteConfigProvider->getConfig();

        $this->assertArrayHasKey('payment', $config);
        $this->assertArrayHasKey('adyen', $config['payment']);
        $this->assertArrayHasKey('virtualQuote', $config['payment']['adyen']);
        $this->assertSame(
            $paymentMethodsResponse,
            $config['payment']['adyen']['virtualQuote']['paymentMethodsResponse']
        );
        $this->assertSame(
            $connectedTerminalsResponse,
            $config['payment']['adyen']['virtualQuote']['connectedTerminals']
        );
    }

    #[Test]
    public function testGetConfigReturnsEmptyArrayForVirtualQuoteWhenBothInactive(): void
    {
        $this->quoteMock->method('isVirtual')->willReturn(true);

        $this->configHelperMock->method('getIsPaymentMethodsActive')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $this->configHelperMock->method('getAdyenPosCloudConfigData')
            ->with('active', self::STORE_ID, true)
            ->willReturn(false);

        $this->paymentMethodsHelperMock->expects($this->never())
            ->method('getApiResponse');

        $this->connectedTerminalsHelperMock->expects($this->never())
            ->method('getConnectedTerminals');

        $config = $this->adyenVirtualQuoteConfigProvider->getConfig();

        $this->assertSame([], $config);
    }
}
