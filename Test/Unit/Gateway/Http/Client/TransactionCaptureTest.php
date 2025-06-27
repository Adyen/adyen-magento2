<?php

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\Client;
use Adyen\Model\Checkout\ApplicationInfo;
use Adyen\Model\Checkout\PaymentCaptureRequest;
use Adyen\Model\Checkout\PaymentCaptureResponse;
use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout;
use Exception;
use Magento\Payment\Gateway\Http\TransferInterface;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Data;
use Adyen\AdyenException;

class TransactionCaptureTest extends AbstractAdyenTestCase
{
    private $transactionCapture;
    private $transferObject;
    private $request;
    private $adyenHelper;
    private $idempotencyHelper;
    private PlatformInfo $platformInfo;

    protected function setUp(): void
    {
        $this->adyenHelper = $this->createPartialMock(Data::class, [
            'initializeAdyenClientWithClientConfig',
            'initializeModificationsApi',
            'logRequest',
            'logResponse'
        ]);
        $adyenLogger = $this->createMock(AdyenLogger::class);
        $this->idempotencyHelper = $this->createMock(Idempotency::class);
        $this->platformInfo = $this->createMock(PlatformInfo::class);

        $this->transactionCapture = new TransactionCapture(
            $this->adyenHelper,
            $adyenLogger,
            $this->idempotencyHelper,
            $this->platformInfo
        );

        $applicationInfo = $this->createMock(ApplicationInfo::class);
        $this->platformInfo->method('buildApplicationInfo')->willReturn($applicationInfo);

        $this->request = [[
            'amount' => ['value' => 100, 'currency' => 'USD'],
            'paymentPspReference' => 'testPspReference',
            'applicationInfo' => $applicationInfo,
            'idempotencyExtraData' => ['someData']
        ]];

        $this->transferObject = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $this->request,
            'getHeaders' => [],
            'getClientConfig' => []
        ]);
    }

    private function configureAdyenMocks(?array $response = null, ?Exception $exception = null): void
    {
        $adyenClient = $this->createMock(Client::class);
        $checkoutModificationsService = $this->createMock(Checkout\ModificationsApi::class);
        $expectedIdempotencyKey = 'generated_idempotency_key';

        $this->adyenHelper->method('initializeAdyenClientWithClientConfig')->willReturn($adyenClient);
        $this->adyenHelper->method('initializeModificationsApi')->willReturn($checkoutModificationsService);
        $this->platformInfo->method('buildRequestHeaders')->willReturn([]);
        $this->adyenHelper->expects($this->once())->method('logRequest');

        $trimmedRequest = $this->request[0];
        unset($trimmedRequest['idempotencyExtraData']);

        $this->idempotencyHelper->expects($this->once())
            ->method('generateIdempotencyKey')
            ->with(
                $trimmedRequest,
                $this->equalTo(['someData'])
            )
            ->willReturn($expectedIdempotencyKey);

        if ($response) {
            $this->adyenHelper->expects($this->once())->method('logResponse');

            $request = new PaymentCaptureRequest($this->request[0]);

            $responseMock = $this->createMock(PaymentCaptureResponse::class);
            $responseMock->method('toArray')->willReturn($response);

            $requestOptions['idempotencyKey'] = $expectedIdempotencyKey;
            $requestOptions['headers'] = [];

            $checkoutModificationsService->expects($this->once())
                ->method('captureAuthorisedPayment')
                ->with(
                    $this->request[0]['paymentPspReference'],
                    $request,
                    $requestOptions
                )
                ->willReturn($responseMock);
        }

        if ($exception) {
            $checkoutModificationsService->expects($this->once())
                ->method('captureAuthorisedPayment')
                ->willThrowException($exception);
        }
    }

    public function testPlaceRequest()
    {
        $expectedResponse = [
            'capture_amount' => $this->request[0]['amount']['value'],
            'paymentPspReference' => $this->request[0]['paymentPspReference'],
            'applicationInfo' => $this->request[0]['applicationInfo'],
            'formattedModificationAmount' => 'USD 1'
        ];

        $this->configureAdyenMocks($expectedResponse);

        // Call the method under test
        $response = $this->transactionCapture->placeRequest($this->transferObject);

        // Assert that the response is as expected
        $this->assertEquals([$expectedResponse], $response);
    }

    public function testPlaceRequestWithException()
    {
        $expectedException = new AdyenException('Test exception');

        $this->configureAdyenMocks(null, $expectedException);

        // Call the method under test
        $response = $this->transactionCapture->placeRequest($this->transferObject);

        // Assert that the response contains the error message
        $this->assertArrayHasKey('error', $response[0]);
        $this->assertEquals('An error occurred during the capture attempt of authorisation with pspreference testPspReference. Test exception', $response[0]['error']);
    }
}
