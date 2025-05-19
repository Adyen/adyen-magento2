<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenDonationCampaigns;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Magento\Sales\Model\Order;

#[CoversClass(AdyenDonationCampaigns::class)]
class AdyenDonationCampaignsTest extends AbstractAdyenTestCase
{
    private DonationsHelper $donationsHelper;
    private OrderRepository $orderRepository;
    private ChargedCurrency $chargedCurrency;
    private AdyenLogger $adyenLogger;
    private Config $configHelper;
    private Data $adyenHelper;
    private AdyenDonationCampaigns $campaigns;

    protected function setUp(): void
    {
        $this->donationsHelperMock = $this->createMock(DonationsHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->localeHelperMock = $this->createMock(Locale::class);
        $this->currencyObject = $this->createMock(AdyenAmountCurrency::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->donationsHelper = $this->createMock(DonationsHelper::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->chargedCurrency = $this->createMock(ChargedCurrency::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->adyenHelper = $this->createMock(Data::class);

        $this->campaigns = new AdyenDonationCampaigns(
            $this->donationsHelper,
            $this->orderRepository,
            $this->chargedCurrency,
            $this->adyenLogger,
            $this->configHelper,
            $this->adyenHelper
        );
    }

    #[Test]
    public function getCampaignsReturnsJsonEncodedCampaigns(): void
    {
        $storeId = 1;
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $order->method('getEntityId')->willReturn(123);
        $order->method('getStoreId')->willReturn($storeId);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getAdditionalInformation')->with('donationToken')->willReturn('token');

        $this->orderRepository->method('get')->willReturn($order);
         $this->configHelper->method('getMerchantAccount')->willReturn('merchant123');
        $this->adyenHelper->method('getCurrentLocaleCode')->willReturn('en_US');

        $this->donationsHelper->method('fetchDonationCampaigns')->willReturn([
            'donationCampaigns' => [['id' => 'camp123']]
        ]);
        $this->donationsHelper->method('formatCampaign')->willReturn(['id' => 'camp123']);

        $result = $this->campaigns->getCampaigns(10);

        $this->assertJson($result);
        $this->assertStringContainsString('camp123', $result);
    }

    #[Test]
    public function getCampaignsThrowsExceptionIfOrderNotFound(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to retrieve donation campaigns');

        $this->orderRepository->method('get')->willThrowException(new \Exception('Not found'));

        $this->adyenLogger->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to load order'));

        $this->campaigns->getCampaigns(999);
    }

    #[Test]
    public function getCampaignsThrowsExceptionIfNoEntityId(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to retrieve donation campaigns');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(null);

        $this->orderRepository->method('get')->willReturn($order);

        $this->adyenLogger->expects($this->once())->method('error')
            ->with($this->stringContains('no entity ID'));

        $this->campaigns->getCampaigns(12);
    }

    #[Test]
    public function getCampaignDataThrowsExceptionIfDonationTokenMissing(): void
    {
        $this->expectException(LocalizedException::class);

        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getAdditionalInformation')->with('donationToken')->willReturn(null);

        $this->adyenLogger->expects($this->once())->method('error')
            ->with($this->stringContains('Missing donation token'));

        $this->campaigns->getCampaignData($order);
    }

    #[Test]
    public function getCampaignDataThrowsExceptionOnFetchFailure(): void
    {
        $this->expectException(LocalizedException::class);

        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $amountCurrency = $this->createConfiguredMock(\Adyen\Payment\Model\AdyenAmountCurrency::class, [
            'getCurrencyCode' => 'EUR'
        ]);

        $order->method('getStoreId')->willReturn(1);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getAdditionalInformation')->with('donationToken')->willReturn('token');

        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($amountCurrency);
        $this->configHelper->method('getMerchantAccount')->willReturn('merchant123');
        $this->adyenHelper->method('getCurrentLocaleCode')->willReturn('en_US');

        $this->donationsHelper->method('fetchDonationCampaigns')
            ->willThrowException(new \Exception('Failed'));

        $this->adyenLogger->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to fetch donation campaigns'));

        $this->campaigns->getCampaignData($order);
    }
}
