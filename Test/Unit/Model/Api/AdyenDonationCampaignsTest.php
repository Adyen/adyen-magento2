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
use Magento\Sales\Model\Order;
use Adyen\Payment\Model\AdyenAmountCurrency;
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
        $orderId = 100;
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn($orderId);

        $this->orderRepositoryMock->method('get')->willReturn($orderMock);

        $this->adyenDonationCampaigns = $this->getMockBuilder(AdyenDonationCampaigns::class)
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

        $this->adyenDonationCampaigns->expects($this->once())
            ->method('getCampaignData')
            ->with($orderMock)
            ->willReturn(json_encode(['key' => 'value']));

        $result = $this->adyenDonationCampaigns->getCampaigns($orderId);
        $this->assertEquals(json_encode(['key' => 'value']), $result);
    }

    public function testGetCampaignsThrowsIfOrderLoadFails(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to retrieve donation campaigns');

        $orderId = 999;
        $this->orderRepositoryMock->method('get')->willThrowException(new \Exception('DB fail'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains("Failed to load order with ID"));

        $this->adyenDonationCampaigns->getCampaigns($orderId);
    }

    public function testGetCampaignsThrowsIfNoEntityId(): void
    {
        $this->expectException(LocalizedException::class);

        $orderId = 101;
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn(null);

        $this->orderRepositoryMock->method('get')->willReturn($orderMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains("Order ID $orderId has no entity ID"));

        $this->adyenDonationCampaigns->getCampaigns($orderId);
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

    public function testGetCampaignDataThrowsIfNoDonationToken(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to retrieve donation campaigns');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getAdditionalInformation')->with('donationToken')->willReturn(null);

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Missing donation token'));

        $this->adyenDonationCampaigns->getCampaignData($orderMock);
    }
}
