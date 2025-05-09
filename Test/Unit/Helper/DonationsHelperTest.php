<?php

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Model\Checkout\DonationCampaignsRequest;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Adyen\Client;
use Adyen\Model\Checkout\DonationCampaignsResponse;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout;

class DonationsHelperTest extends AbstractAdyenTestCase
{
    private $donationsHelper;
    private $adyenHelperMock;
    private $adyenLoggerMock;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->donationsHelper = new DonationsHelper(
            $contextMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock
        );
    }

    public function testFetchDonationCampaignsSuccess(): void
    {
        $storeId = 1;
        $payload = [
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ];

        $clientMock = $this->createMock(Client::class);
        $donationsApiMock = $this->createMock(Checkout\DonationsApi::class);

        $responseMock = $this->createMock(DonationCampaignsResponse::class);

        $expected = ['donationCampaigns' => [['id' => 'abc']]];

        $this->adyenHelperMock->method('initializeAdyenClient')->with($storeId)->willReturn($clientMock);
        $this->adyenHelperMock->method('initializeDonationsApi')->with($clientMock)->willReturn($donationsApiMock);
        $donationsApiMock->method('donationCampaigns')->with($this->isInstanceOf(DonationCampaignsRequest::class))
            ->willReturn($responseMock);
        $responseMock->method('toArray')->willReturn($expected);

        $result = $this->donationsHelper->fetchDonationCampaigns($payload, $storeId);
        $this->assertEquals($expected, $result);
    }

    public function testFetchDonationCampaignsThrowsAndLogs(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to retrieve donation campaigns');

        $storeId = 1;
        $payload = [
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ];

        $clientMock = $this->createMock(\Adyen\Client::class);

        $this->adyenHelperMock->method('initializeAdyenClient')->with($storeId)->willReturn($clientMock);
        $this->adyenHelperMock->method('initializeDonationsApi')
            ->with($clientMock)
            ->willThrowException(new AdyenException('API failed'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error fetching donation campaigns'),
                $this->arrayHasKey('exception')
            );

        $this->donationsHelper->fetchDonationCampaigns($payload, $storeId);
    }

    public function testFormatCampaignWithData(): void
    {
        $response = [
            'donationCampaigns' => [[
                'nonprofitName' => 'Red Cross',
                'nonprofitDescription' => 'Helping people',
                'nonprofitUrl' => 'https://example.com',
                'logoUrl' => 'https://example.com/logo.png',
                'bannerUrl' => 'https://example.com/banner.png',
                'termsAndConditionsUrl' => 'https://example.com/terms',
                'donation' => ['amount' => 500, 'type' => 'roundup'],
                'causeName' => 'Adyen Giving'
            ]]
        ];

        $expected = [
            'nonprofitName' => 'Red Cross',
            'nonprofitDescription' => 'Helping people',
            'nonprofitUrl' => 'https://example.com',
            'logoUrl' => 'https://example.com/logo.png',
            'bannerUrl' => 'https://example.com/banner.png',
            'termsAndConditionsUrl' => 'https://example.com/terms',
            'donation' => ['amount' => 500, 'type' => ''],
            'causeName' => 'Adyen Giving'
        ];

        $result = $this->donationsHelper->formatCampaign($response);
        $this->assertEquals($expected, $result);
    }

    public function testFormatCampaignWithEmptyData(): void
    {
        $this->assertEquals([], $this->donationsHelper->formatCampaign([]));
        $this->assertEquals([], $this->donationsHelper->formatCampaign(['donationCampaigns' => []]));
    }

    public function testSetDonationCampaignId(): void
    {
        $orderMock = $this->createMock(Order::class);
        $paymentMock = $this->createMock(Payment::class);

        $orderMock->method('getPayment')->willReturn($paymentMock);
        $paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('donationCampaignId', 'CAMPAIGN_ID');

        $orderMock->expects($this->once())->method('save');

        $this->donationsHelper->setDonationCampaignId($orderMock, 'CAMPAIGN_ID');
    }
}
