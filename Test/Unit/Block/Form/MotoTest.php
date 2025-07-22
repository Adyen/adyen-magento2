<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Block\Form;

use Adyen\Payment\Block\Form\Moto;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Installments;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Backend\Model\Session\Quote;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\State;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\Config as PaymentConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Store\Model\Store;

#[CoversClass(Moto::class)]
class MotoTest extends AbstractAdyenTestCase
{
    private Moto $motoBlock;

    private MockObject $adyenHelper;
    private MockObject $checkoutSession;
    private MockObject $installmentsHelper;
    private MockObject $adyenLogger;
    private MockObject $configHelper;
    private MockObject $backendSession;
    private MockObject $localeHelper;
    private MockObject $storeManager;
    private MockObject $quote;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $paymentConfig = $this->createMock(PaymentConfig::class);
        $this->adyenHelper = $this->createMock(Data::class);
        $this->checkoutSession = $this->createMock(Session::class);
        $this->installmentsHelper = $this->createMock(Installments::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->backendSession = $this->createMock(Quote::class);
        $this->localeHelper = $this->createMock(Locale::class);
        $appState = $this->createMock(State::class);
        $this->quote = $this->createMock(QuoteModel::class);
        $store = $this->createMock(Store::class);

        $this->storeManager = $this->createMock(\Magento\Store\Model\StoreManagerInterface::class);
        $context->method('getAppState')->willReturn($appState);
        $context->method('getStoreManager')->willReturn($this->storeManager);

        $this->checkoutSession->method('getQuote')->willReturn($this->quote);
        $this->backendSession->method('getQuote')->willReturn($this->quote);
        $this->quote->method('getStore')->willReturn($store);
        $store->method('getId')->willReturn(1);

        $this->motoBlock = new Moto(
            $context,
            $paymentConfig,
            $this->adyenHelper,
            $this->checkoutSession,
            $this->installmentsHelper,
            $this->adyenLogger,
            $this->configHelper,
            $this->backendSession,
            $this->localeHelper
        );

    }

    #[Test]
    public function testGetCheckoutEnvironment(): void
    {
        $this->adyenHelper->expects(self::once())
            ->method('getCheckoutEnvironment')
            ->with(1)
            ->willReturn('test');

        $this->assertSame('test', $this->motoBlock->getCheckoutEnvironment());
    }

    #[Test]
    public function testGetLocale(): void
    {
        $this->localeHelper->expects(self::once())
            ->method('getStoreLocale')
            ->with(1)
            ->willReturn('en_US');

        $this->assertSame('en_US', $this->motoBlock->getLocale());
    }

    #[Test]
    public function testGetCcAvailableTypesByAlt(): void
    {
        $this->adyenHelper->method('getAdyenCcTypes')->willReturn([
            'visa' => ['code_alt' => 'VI'],
            'mc' => ['code_alt' => 'MC']
        ]);
        $this->configHelper->method('getAdyenCcConfigData')->with('cctypes')->willReturn('visa,mc');

        $expected = ['VI' => 'visa', 'MC' => 'mc'];
        $this->assertSame($expected, $this->motoBlock->getCcAvailableTypesByAlt());
    }

    #[Test]
    public function testGetFormattedInstallmentsReturnsData(): void
    {
        $quoteData = ['grand_total' => 100];
        $this->quote->method('getData')->willReturn($quoteData);
        $this->storeManager->method('getStore')->willReturn($this->quote->getStore());
        $this->configHelper->method('getAdyenCcConfigData')->willReturn('installments');
        $this->adyenHelper->method('getAdyenCcTypes')->willReturn([]);
        $this->installmentsHelper->method('formatInstallmentsConfig')->willReturn('{}');

        $this->assertSame('{}', $this->motoBlock->getFormattedInstallments());
    }

    #[Test]
    public function testGetCountryId(): void
    {
        $billingAddress = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $billingAddress->method('getCountryId')->willReturn('NL');

        $this->quote->method('getBillingAddress')->willReturn($billingAddress);

        $this->assertSame('NL', $this->motoBlock->getCountryId());
    }

}
