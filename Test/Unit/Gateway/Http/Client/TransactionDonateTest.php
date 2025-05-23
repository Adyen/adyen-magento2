<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\ApplicationInfo;
use Adyen\Model\Checkout\DonationPaymentRequest;
use Adyen\Model\Checkout\DonationPaymentResponse;
use Adyen\Payment\Gateway\Http\Client\TransactionDonate;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Service\Checkout\DonationsApi;
use Magento\Payment\Gateway\Http\TransferInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransactionDonateTest extends TestCase
{
    private TransactionDonate $transactionDonate;
    private MockObject $adyenHelper;
    private MockObject $idempotencyHelper;
    private MockObject $platformInfo;
    private MockObject $transferObject;
    private MockObject $client;
    private MockObject $donationsApi;
    private MockObject $applicationInfo;

    private array $request;

    protected function setUp(): void
    {
        $this->adyenHelper = $this->createMock(Data::class);
        $this->idempotencyHelper = $this->createMock(Idempotency::class);
        $this->platformInfo = $this->createMock(PlatformInfo::class);
        $this->client = $this->createMock(Client::class);
        $this->donationsApi = $this->createMock(DonationsApi::class);
        $this->applicationInfo = $this->createMock(ApplicationInfo::class);

        // Mock client init
        $this->adyenHelper->method('initializeAdyenClient')->willReturn($this->client);

        // Inject the class under test
        $this->transactionDonate = new TransactionDonate(
            $this->adyenHelper,
            $this->idempotencyHelper,
            $this->platformInfo
        );

        // Mock DonationsApi creation
        $this->adyenHelper->method('initializeDonationsApi')->with($this->client)->willReturn($this->donationsApi);

        $this->request = [
            'donationToken' => 'abc123',
            'merchantAccount' => 'TestMerchant'
        ];

        $this->transferObject = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $this->request,
            'getHeaders' => ['idempotencyExtraData' => ['donation']]
        ]);
    }

    public function testPlaceRequestReturnsSuccessfulResponse(): void
    {
        $idempotencyKey = 'generated-key';
        $responseArray = ['status' => 'received'];

        $this->platformInfo->method('buildRequestHeaders')->willReturn([]);
        $this->platformInfo->method('buildApplicationInfo')->willReturn($this->applicationInfo);
        $this->idempotencyHelper->method('generateIdempotencyKey')->willReturn($idempotencyKey);

        // Expect logRequest and logResponse to be called
        $this->adyenHelper->expects($this->once())->method('logRequest');
        $this->adyenHelper->expects($this->once())->method('logResponse');

        $responseObj = $this->createMock(DonationPaymentResponse::class);
        $responseObj->method('toArray')->willReturn($responseArray);

        $this->donationsApi->expects($this->once())
            ->method('donations')
            ->with(
                $this->isInstanceOf(DonationPaymentRequest::class),
                [
                    'idempotencyKey' => $idempotencyKey,
                    'headers' => []
                ]
            )
            ->willReturn($responseObj);

        $result = $this->transactionDonate->placeRequest($this->transferObject);

        $this->assertEquals($responseArray, $result);
    }

    public function testPlaceRequestHandlesAdyenException(): void
    {
        $idempotencyKey = 'generated-key';

        $this->platformInfo->method('buildRequestHeaders')->willReturn([]);
        $this->platformInfo->method('buildApplicationInfo')->willReturn($this->applicationInfo);
        $this->idempotencyHelper->method('generateIdempotencyKey')->willReturn($idempotencyKey);

        $this->adyenHelper->expects($this->once())->method('logRequest');
        $this->adyenHelper->expects($this->once())->method('logResponse');

        $this->donationsApi->method('donations')->willThrowException(new AdyenException('Donation failed'));

        $result = $this->transactionDonate->placeRequest($this->transferObject);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Donation failed', $result['error']);
    }
}
