<?php

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Client;
use Adyen\Config as AdyenConfig;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Model\Checkout\DonationCampaigns;
use Adyen\Model\Checkout\DonationCampaignsResponse;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Adyen\Service\Checkout;
use Magento\Framework\Exception\NoSuchEntityException;

class DonationsHelperTest extends AbstractAdyenTestCase
{
    private DonationsHelper $donationsHelper;
    private Data $adyenHelperMock;

    /**
     * @var Config
     */
    private Config $configHelperMock;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->configHelperMock = $this->createMock(Config::class);

        $this->donationsHelper = new DonationsHelper(
            $contextMock,
            $this->adyenHelperMock,
            $this->configHelperMock
        );

    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
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

    public function testFetchDonationCampaignsThrowsAdyenException(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/^Error fetching donation campaigns:/');

        $payload = [
            'merchantAccount' => 'TestMerchant',
            'currency' => 'EUR',
            'locale' => 'en-US'
        ];
        $storeId = 1;

        $clientMock = $this->createMock(\Adyen\Client::class);

        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($clientMock);
        $this->adyenHelperMock->method('initializeDonationsApi')->willThrowException(
            new \Adyen\AdyenException('Something went wrong')
        );

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
