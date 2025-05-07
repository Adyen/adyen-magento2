<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Api\AdyenDonationCampaigns;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class AdyenDonationCampaignsTest extends TestCase
{
    private $donationsHelperMock;
    private $orderRepositoryMock;
    private $chargedCurrencyMock;
    private $adyenDonationCampaigns;

    protected function setUp(): void
    {
        $this->paymentMock = $this->createMock(Payment::class);
        $this->donationsHelperMock = $this->createMock(DonationsHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);

        $this->adyenDonationCampaigns = new AdyenDonationCampaigns(
            $this->donationsHelperMock,
            $this->orderRepositoryMock,
            $this->chargedCurrencyMock
        );
    }

    public function testGetCampaignsSuccess(): void
    {
        $orderId = 100;
        $payload = json_encode([
            'currency' => 'EUR'
        ]);

        $orderMock = $this->createConfiguredMock(OrderInterface::class, [
            'getEntityId' => $orderId
        ]);

        $this->orderRepositoryMock->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        // Delegate to getCampaignData — mock with real test instead
        $campaignDataMock = '{"donationCampaigns":[{"reference":"abc"}]}';

        $adyenMock = $this->getMockBuilder(AdyenDonationCampaigns::class)
            ->setConstructorArgs([
                $this->donationsHelperMock,
                $this->orderRepositoryMock,
                $this->chargedCurrencyMock
            ])
            ->onlyMethods(['getCampaignData'])
            ->getMock();

        $adyenMock->expects($this->once())
            ->method('getCampaignData')
            ->with($orderMock, $payload)
            ->willReturn($campaignDataMock);

        $result = $adyenMock->getCampaigns($orderId, $payload);
        $this->assertEquals($campaignDataMock, $result);
    }

    public function testGetCampaignsThrowsIfOrderNotFound(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Order not found.');

        $orderId = 200;
        $payload = '{}';

        $orderMock = $this->createConfiguredMock(OrderInterface::class, [
            'getEntityId' => null
        ]);

        $this->orderRepositoryMock->method('get')->with($orderId)->willReturn($orderMock);

        $this->adyenDonationCampaigns->getCampaigns($orderId, $payload);
    }

    public function testGetCampaignDataThrowsIfNoDonationToken(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Donation failed!');

        $payload = json_encode(['currency' => 'EUR']);


        $this->paymentMock->method('getAdditionalInformation')->with('donationToken')->willReturn(null);

        $orderMock = $this->createConfiguredMock(OrderInterface::class, [
            'getPayment' => $this->paymentMock
        ]);

        $this->adyenDonationCampaigns->getCampaignData($orderMock, $payload);
    }

    public function testGetCampaignDataSuccess(): void
    {
        $payloadArray = ['currency' => 'EUR'];
        $payload = json_encode($payloadArray);

        $this->paymentMock->method('getAdditionalInformation')
            ->with('donationToken')
            ->willReturn('valid_token');

        $orderMock = $this->createMock(\Magento\Sales\Model\Order::class);
        $orderMock->method('getPayment')->willReturn($this->paymentMock);
        $orderMock->method('getStoreId')->willReturn(1);

        $adyenAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $adyenAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($orderMock, false)
            ->willReturn($adyenAmountCurrencyMock);

        $campaignResponse = ['donationCampaigns' => [['id' => 'abc']]];
        $formattedResponse = ['donationCampaigns' => [['reference' => 'abc']]];

        $this->donationsHelperMock->expects($this->once())
            ->method('fetchDonationCampaigns')
            ->with($payloadArray, 1)
            ->willReturn($campaignResponse);

        $this->donationsHelperMock->expects($this->once())
            ->method('formatCampaign')
            ->with($campaignResponse)
            ->willReturn($formattedResponse);

        $result = $this->adyenDonationCampaigns->getCampaignData($orderMock, $payload);

        $this->assertEquals(json_encode($formattedResponse), $result);
    }

    public function testGetCampaignDataThrowsIfCurrencyMismatch(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Donation failed!');

        $payload = json_encode(['currency' => 'USD']);

        $this->paymentMock->method('getAdditionalInformation')
            ->with('donationToken')
            ->willReturn('valid_token');

        $orderMock = $this->createMock(\Magento\Sales\Model\Order::class);
        $orderMock->method('getPayment')->willReturn($this->paymentMock);
        $orderMock->method('getStoreId')->willReturn(1);

        $adyenAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $adyenAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($orderMock, false)
            ->willReturn($adyenAmountCurrencyMock);

        $this->adyenDonationCampaigns->getCampaignData($orderMock, $payload);
    }

}
