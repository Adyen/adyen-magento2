<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Model\Api\AdyenDonationCampaigns;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class AdyenDonationCampaignsTest extends AbstractAdyenTestCase
{
    private DonationsHelper $donationsHelperMock;
    private OrderRepository $orderRepositoryMock;
    private AdyenDonationCampaigns $adyenDonationCampaigns;

    protected function setUp(): void
    {
        $this->donationsHelperMock = $this->createMock(DonationsHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);

        $this->adyenDonationCampaigns = new AdyenDonationCampaigns(
            $this->donationsHelperMock,
            $this->orderRepositoryMock
        );
    }

    /**
     * @throws LocalizedException
     */
    public function testGetCampaignsSuccess(): void
    {
        $orderId = 123;
        $payloadArray = [
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ];
        $payloadJson = json_encode($payloadArray);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);
        $orderMock->method('getStoreId')->willReturn(1);

        $campaignResponse = ['donationCampaigns' => [['id' => '123']]];
        $formattedResponse = ['donationCampaigns' => [['reference' => '123']]];

        $this->orderRepositoryMock->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        $this->donationsHelperMock->expects($this->once())
            ->method('validatePayload')
            ->with($payloadArray);

        $this->donationsHelperMock->expects($this->once())
            ->method('fetchDonationCampaigns')
            ->with($payloadArray, 1)
            ->willReturn($campaignResponse);

        $this->donationsHelperMock->expects($this->once())
            ->method('formatCampaign')
            ->with($campaignResponse)
            ->willReturn($formattedResponse);

        $result = $this->adyenDonationCampaigns->getCampaigns($orderId, $payloadJson);
        $this->assertEquals(json_encode($formattedResponse), $result);
    }

    public function testGetCampaignsThrowsIfOrderNotFound(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Order not found.');

        $orderId = 123;
        $payloadJson = json_encode([
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ]);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn(null); // Order not found

        $this->orderRepositoryMock->method('get')->with($orderId)->willReturn($orderMock);

        $this->adyenDonationCampaigns->getCampaigns($orderId, $payloadJson);
    }
}
