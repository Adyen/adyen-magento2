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
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\Exception;

class AdyenDonationCampaignsTest extends AbstractAdyenTestCase
{
    private $donationsHelperMock;
    private $orderRepositoryMock;
    private $chargedCurrencyMock;
    private $adyenLoggerMock;
    private $configHelperMock;
    private $adyenHelperMock;
    private $adyenDonationCampaigns;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->donationsHelperMock = $this->createMock(DonationsHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenHelperMock = $this->createMock(AdyenHelper::class);
        $this->currencyObject = $this->createMock(AdyenAmountCurrency::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->orderMock = $this->createMock(Order::class);

        $this->adyenDonationCampaigns = new AdyenDonationCampaigns(
            $this->donationsHelperMock,
            $this->orderRepositoryMock,
            $this->chargedCurrencyMock,
            $this->adyenLoggerMock,
            $this->configHelperMock,
            $this->adyenHelperMock
        );
    }

    public function testGetCampaignsSuccess(): void
    {
        $orderId = 123;
        $this->orderMock->method('getEntityId')->willReturn($orderId);

        $this->orderRepositoryMock->expects($this->once())
            ->method('get')->with($orderId)->willReturn($this->orderMock);

        $expectedJson = '{"reference":"abc"}';

        $adyenDonationCampaigns = $this->getMockBuilder(AdyenDonationCampaigns::class)
            ->setConstructorArgs([
                $this->donationsHelperMock,
                $this->orderRepositoryMock,
                $this->chargedCurrencyMock,
                $this->adyenLoggerMock,
                $this->configHelperMock,
                $this->adyenHelperMock
            ])
            ->onlyMethods(['getCampaignData'])
            ->getMock();

        $adyenDonationCampaigns->expects($this->once())
            ->method('getCampaignData')
            ->with($this->orderMock)
            ->willReturn($expectedJson);

        $result = $adyenDonationCampaigns->getCampaigns($orderId);
        $this->assertEquals($expectedJson, $result);
    }

    public function testGetCampaignsWithException(): void
    {
        $orderId = 999;
        $this->orderRepositoryMock->method('get')->willThrowException(new \Exception('Not found'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains("Failed to load order with ID $orderId"));

        $result = $this->adyenDonationCampaigns->getCampaigns($orderId);
        $this->assertEquals('null', $result);
    }

    public function testGetCampaignsWithEmptyEntityId(): void
    {
        $orderId = 456;
        $this->orderMock->method('getEntityId')->willReturn(null);

        $this->orderRepositoryMock->method('get')->willReturn($this->orderMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains("Order ID $orderId has no entity ID"));

        $result = $this->adyenDonationCampaigns->getCampaigns($orderId);
        $this->assertEquals('null', $result);
    }

    public function testGetCampaignDataSuccess(): void
    {
        $currencyCode = 'EUR';
        $merchantAccount = 'TestMerchant';
        $locale = 'en_US';
        $campaignId = 'abc123';

        $this->paymentMock->method('getAdditionalInformation')->with('donationToken')->willReturn('token123');
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStoreId')->willReturn(1);

        $this->currencyObject->method('getCurrencyCode')->willReturn($currencyCode);

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($this->orderMock, false)
            ->willReturn($this->currencyObject);

        $this->configHelperMock->method('getMerchantAccount')->willReturn($merchantAccount);
        $this->adyenHelperMock->method('getCurrentLocaleCode')->willReturn($locale);

        $donationCampaignsResponse = ['donationCampaigns' => [['id' => $campaignId]]];
        $formattedCampaign = ['reference' => 'abc'];

        $this->donationsHelperMock->method('fetchDonationCampaigns')->willReturn($donationCampaignsResponse);
        $this->donationsHelperMock->method('formatCampaign')->willReturn($formattedCampaign);
        $this->donationsHelperMock->expects($this->once())
            ->method('setDonationCampaignId')
            ->with($this->orderMock, $campaignId);

        $result = $this->adyenDonationCampaigns->getCampaignData($this->orderMock);
        $this->assertEquals(json_encode($formattedCampaign), $result);
    }

    public function testGetCampaignDataMissingDonationToken(): void
    {
        $this->paymentMock->method('getAdditionalInformation')->with('donationToken')->willReturn(null);

        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Missing donation token'));

        $result = $this->adyenDonationCampaigns->getCampaignData($this->orderMock);
        $this->assertEquals('null', $result);
    }

    public function testGetCampaignDataThrowsException(): void
    {
        $storeId = 1;

        $this->paymentMock->method('getAdditionalInformation')->with('donationToken')->willReturn('token123');

        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStoreId')->willReturn($storeId);

        $this->currencyObject->method('getCurrencyCode')->willReturn('EUR');
        $this->chargedCurrencyMock->method('getOrderAmountCurrency')->willReturn($this->currencyObject);

        $this->configHelperMock->method('getMerchantAccount')->willReturn('account');
        $this->adyenHelperMock->method('getCurrentLocaleCode')->willReturn('en_US');

        $this->donationsHelperMock->method('fetchDonationCampaigns')
            ->willThrowException(new \Exception('API failure'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to fetch donation campaigns'));

        $result = $this->adyenDonationCampaigns->getCampaignData($this->orderMock);
        $this->assertEquals('null', $result);
    }
}
