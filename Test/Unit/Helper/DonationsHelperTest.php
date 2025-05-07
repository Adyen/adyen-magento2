<?php

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Client;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Model\Checkout\DonationCampaignsResponse;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Adyen\Service\Checkout;
use Adyen\Payment\Logger\AdyenLogger;

class DonationsHelperTest extends AbstractAdyenTestCase
{
    private DonationsHelper $donationsHelper;
    private Data $adyenHelperMock;

    /**
     * @var Config
     */
    private Config $configHelperMock;

    private AdyenLogger $adyenLoggerMock;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->donationsHelper = new DonationsHelper(
            $contextMock,
            $this->adyenHelperMock,
            $this->configHelperMock,
            $this->adyenLoggerMock
        );

    }

    public function testFetchDonationCampaignsSuccess(): void
    {
        $payload = [
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ];
        $storeId = 1;

        $adyenClientMock = $this->createMock(Client::class);
        $donationServiceMock = $this->createMock(Checkout\DonationsApi::class);

        $campaignResponseMock = $this->createMock(DonationCampaignsResponse::class);

        $expectedArray = ['donationCampaigns' => [['id' => 'test-campaign']]];

        $campaignResponseMock->method('toArray')->willReturn($expectedArray);
        $donationServiceMock->method('donationCampaigns')->willReturn($campaignResponseMock);

        $this->adyenHelperMock->method('initializeAdyenClient')->with($storeId)->willReturn($adyenClientMock);
        $this->adyenHelperMock->method('initializeDonationsApi')->with($adyenClientMock)->willReturn($donationServiceMock);

        $result = $this->donationsHelper->fetchDonationCampaigns($payload, $storeId);
        $this->assertEquals($expectedArray, $result);
    }

    public function testFetchDonationCampaignsThrowsLocalizedExceptionWithGenericMessage(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to retrieve donation campaigns. Please try again later.');

        $payload = [
            'currency' => 'EUR',
            'locale' => 'en-US'
        ];
        $storeId = 1;

        $clientMock = $this->createMock(\Adyen\Client::class);

        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($clientMock);
        $this->adyenHelperMock->method('initializeDonationsApi')->willThrowException(
            new \Adyen\AdyenException('Something went wrong')
        );

        $this->configHelperMock->method('getMerchantAccount')->with($storeId)->willReturn('TestMerchant');

        // Optionally assert logging (if you mock $adyenLogger and inject it)
         $this->adyenLoggerMock->expects($this->once())
             ->method('error')
             ->with('Error fetching donation campaigns', $this->callback(function ($context) {
                 return isset($context['exception']) && $context['exception'] instanceof \Adyen\AdyenException;
             }));

        $this->donationsHelper->fetchDonationCampaigns($payload, $storeId);
    }


    public function testFormatCampaignReturnsFormattedFirstCampaign(): void
    {
        $donationResponse = [
            'donationCampaigns' => [[
                'nonprofitName' => 'Red Cross',
                'nonprofitDescription' => 'Helping people in need',
                'id' => 'campaign-1',
                'nonprofitUrl' => 'https://redcross.org',
                'logoUrl' => 'https://logo.png',
                'bannerUrl' => 'https://banner.png',
                'termsAndConditionsUrl' => 'https://tandc.com',
                'donation' => ['amount' => 500]
            ]]
        ];

        $expected = [
            'donationCampaigns' => [[
                'nonprofitName' => 'Red Cross',
                'description' => 'Helping people in need',
                'reference' => 'campaign-1',
                'nonprofitUrl' => 'https://redcross.org',
                'logoUrl' => 'https://logo.png',
                'bannerUrl' => 'https://banner.png',
                'termsAndConditionsUrl' => 'https://tandc.com',
                'donation' => ['amount' => 500]
            ]]
        ];

        $this->assertEquals($expected, $this->donationsHelper->formatCampaign($donationResponse));
    }

    public function testFormatCampaignReturnsEmptyArrayWhenNoCampaigns(): void
    {
        $donationResponse = ['donationCampaigns' => []];
        $expected = ['donationCampaigns' => []];

        $this->assertEquals($expected, $this->donationsHelper->formatCampaign($donationResponse));
    }

    public function testFormatCampaignReturnsEmptyArrayWhenKeyIsMissing(): void
    {
        $donationResponse = []; // No 'donationCampaigns' key
        $expected = ['donationCampaigns' => []];

        $this->assertEquals($expected, $this->donationsHelper->formatCampaign($donationResponse));
    }
}
