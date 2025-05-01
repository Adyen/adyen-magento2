<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Api\GuestAdyenDonationCampaigns;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class GuestAdyenDonationCampaignsTest extends AbstractAdyenTestCase
{
    protected function setUp(): void
    {
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $this->donationsHelperMock = $this->createMock(DonationsHelper::class);
        $this->orderCollectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);

        $this->guestDonationCampaigns = new GuestAdyenDonationCampaigns(
            $this->quoteIdMaskFactoryMock,
            $this->donationsHelperMock,
            $this->orderCollectionFactoryMock,
            $this->quoteRepositoryMock
        );
    }

    /**
     * @throws LocalizedException
     */
    public function testGetCampaignsSuccess(): void
    {
        $cartId = 'abc123';
        $payloadData = [
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ];
        $payloadJson = json_encode($payloadData);
        $quoteId = 42;
        $storeId = 1;

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn($quoteId);

        $this->quoteIdMaskFactoryMock->method('create')
            ->willReturn($quoteIdMaskMock);

        // Mock Quote
        $quoteMock = $this->createMock(Quote::class);
        $quoteMock->method('getStoreId')->willReturn($storeId);
        $this->quoteRepositoryMock->method('get')->with($quoteId)->willReturn($quoteMock);

        // Expectations on DonationsHelper
        $this->donationsHelperMock->expects($this->once())
            ->method('validatePayload')->with($payloadData);

        $campaignResponse = ['donationCampaigns' => [['id' => 'abc']]];
        $formattedResponse = ['donationCampaigns' => [['reference' => 'abc']]];

        $this->donationsHelperMock->expects($this->once())
            ->method('fetchDonationCampaigns')
            ->with($payloadData, $storeId)
            ->willReturn($campaignResponse);

        $this->donationsHelperMock->expects($this->once())
            ->method('formatCampaign')
            ->with($campaignResponse)
            ->willReturn($formattedResponse);

        $result = $this->guestDonationCampaigns->getCampaigns($cartId, $payloadJson);

        $this->assertEquals(json_encode($formattedResponse), $result);
    }

    public function testGetCampaignsThrowsExceptionWhenQuoteIdNotFound(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid cart ID.');

        $cartId = 'invalid123';
        $payloadJson = json_encode([
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ]);

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn(null);

        $this->quoteIdMaskFactoryMock->method('create')->willReturn($quoteIdMaskMock);

        $this->guestDonationCampaigns->getCampaigns($cartId, $payloadJson);
    }
}
