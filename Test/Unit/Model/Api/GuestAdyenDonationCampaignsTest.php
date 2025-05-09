<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Model\Api\AdyenDonationCampaigns;
use Adyen\Payment\Model\Api\GuestAdyenDonationCampaigns;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\Order;

class GuestAdyenDonationCampaignsTest extends AbstractAdyenTestCase
{
    private $quoteIdMaskFactoryMock;
    private $adyenDonationCampaignsMock;
    private $orderRepositoryMock;
    private $guestDonationCampaigns;

    protected function setUp(): void
    {
        $this->quoteIdMaskFactoryMock = $this->createMock(QuoteIdMaskFactory::class);
        $this->adyenDonationCampaignsMock = $this->createMock(AdyenDonationCampaigns::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);

        $this->guestDonationCampaigns = new GuestAdyenDonationCampaigns(
            $this->quoteIdMaskFactoryMock,
            $this->orderRepositoryMock,
            $this->adyenDonationCampaignsMock
        );
    }

    public function testGetCampaignsSuccess(): void
    {
        $cartId = '123';
        $quoteId = 42;

        // Mock QuoteIdMask and its factory
        $quoteIdMaskMock = $this->createGeneratedMock(
            QuoteIdMask::class,
            ['load'],
            ['getQuoteId']
        );

        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn($quoteId);

        $this->quoteIdMaskFactoryMock->method('create')->willReturn($quoteIdMaskMock);

        // Mock Order object
        $orderMock = $this->createMock(Order::class);
        $this->orderRepositoryMock->method('getOrderByQuoteId')->with($quoteId)->willReturn($orderMock);

        $expectedResponse = json_encode(['donationCampaigns' => [['reference' => 'abc']]]);

        // Mock call to getCampaignData
        $this->adyenDonationCampaignsMock->expects($this->once())
            ->method('getCampaignData')
            ->with($orderMock)
            ->willReturn($expectedResponse);

        $result = $this->guestDonationCampaigns->getCampaigns($cartId);

        $this->assertEquals($expectedResponse, $result);
    }

}
