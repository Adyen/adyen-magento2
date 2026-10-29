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
use PHPUnit\Framework\Attributes\DataProvider;

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

    public static function setDonationCampaignDataProvider(): array
    {
        return [
            'fixed amounts' => [
                'donation' => [
                    'currency' => 'EUR',
                    'donationType' => 'fixedAmounts',
                    'values' => [500, 700, 100]
                ],
                'expectedAmounts' => [
                    'type' => 'fixedAmounts',
                    'currency' => 'EUR',
                    'values' => [500, 700, 100],
                    'maxRoundupAmount' => null
                ]
            ],
            'round-up' => [
                'donation' => [
                    'currency' => 'EUR',
                    'donationType' => 'roundup',
                    'maxRoundupAmount' => 100
                ],
                'expectedAmounts' => [
                    'type' => 'roundup',
                    'currency' => 'EUR',
                    'values' => [],
                    'maxRoundupAmount' => 100
                ]
            ],
            'missing donation object' => [
                'donation' => null,
                'expectedAmounts' => [
                    'type' => null,
                    'currency' => null,
                    'values' => [],
                    'maxRoundupAmount' => null
                ]
            ]
        ];
    }

    #[DataProvider('setDonationCampaignDataProvider')]
    public function testSetDonationCampaign(?array $donation, array $expectedAmounts): void
    {
        $campaign = ['id' => 'CAMPAIGN_ID'];
        if ($donation !== null) {
            $campaign['donation'] = $donation;
        }

        $orderMock = $this->createMock(Order::class);
        $paymentMock = $this->createMock(Payment::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $stored = [];
        $paymentMock->expects($this->exactly(2))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use (&$stored, $paymentMock) {
                $stored[$key] = $value;
                return $paymentMock;
            });
        $orderMock->expects($this->once())->method('save');

        $this->donationsHelper->setDonationCampaign($orderMock, $campaign);

        $this->assertSame('CAMPAIGN_ID', $stored['donationCampaignId']);
        $this->assertSame($expectedAmounts, $stored['donationCampaignAmounts']);
    }

    public static function isDonationAmountValidDataProvider(): array
    {
        $fixedAmounts = ['type' => 'fixedAmounts', 'currency' => 'EUR', 'values' => [100, 500, 700]];
        $roundup = ['type' => 'roundup', 'currency' => 'EUR', 'values' => [], 'maxRoundupAmount' => 100];

        return [
            'fixed amount in campaign' => [$fixedAmounts, ['currency' => 'EUR', 'value' => 500], 3900, true],
            'fixed amount above campaign max' => [$fixedAmounts, ['currency' => 'EUR', 'value' => 800], 3900, false],
            'fixed amount between campaign values' => [$fixedAmounts, ['currency' => 'EUR', 'value' => 300], 3900, false],
            'fixed amount as numeric string' => [$fixedAmounts, ['currency' => 'EUR', 'value' => '500'], 3900, false],
            'fixed amount as float' => [$fixedAmounts, ['currency' => 'EUR', 'value' => 500.0], 3900, false],
            'zero amount' => [$fixedAmounts, ['currency' => 'EUR', 'value' => 0], 3900, false],
            'negative amount' => [$fixedAmounts, ['currency' => 'EUR', 'value' => -500], 3900, false],
            'missing value' => [$fixedAmounts, ['currency' => 'EUR'], 3900, false],
            'currency differs from campaign' => [$fixedAmounts, ['currency' => 'USD', 'value' => 500], 3900, false],
            'no campaign amounts stored' => [null, ['currency' => 'EUR', 'value' => 500], 3900, false],
            'campaign amounts not an array' => ['invalid', ['currency' => 'EUR', 'value' => 500], 3900, false],
            'unsupported campaign type' => [
                ['type' => 'unknown', 'currency' => 'EUR', 'values' => [500]],
                ['currency' => 'EUR', 'value' => 500],
                3900,
                false
            ],
            'round-up to next multiple' => [$roundup, ['currency' => 'EUR', 'value' => 63], 3937, true],
            'round-up with wrong amount' => [$roundup, ['currency' => 'EUR', 'value' => 37], 3937, false],
            'round-up of exact multiple donates max amount' => [$roundup, ['currency' => 'EUR', 'value' => 100], 3900, true],
            'round-up without max amount' => [
                ['type' => 'roundup', 'currency' => 'EUR', 'values' => [], 'maxRoundupAmount' => null],
                ['currency' => 'EUR', 'value' => 63],
                3937,
                false
            ]
        ];
    }

    #[DataProvider('isDonationAmountValidDataProvider')]
    public function testIsDonationAmountValid(
        mixed $campaignAmounts,
        array $amount,
        int $originalPaymentAmount,
        bool $expectedResult
    ): void {
        $this->adyenLoggerMock->expects($expectedResult ? $this->never() : $this->once())
            ->method('addAdyenWarning')
            ->with($this->stringContains('Donation rejected'));

        $this->assertSame(
            $expectedResult,
            $this->donationsHelper->isDonationAmountValid($campaignAmounts, $amount, $originalPaymentAmount)
        );
    }
}
