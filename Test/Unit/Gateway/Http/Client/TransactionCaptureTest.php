<?php

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\Client;
use Adyen\Model\Checkout\ApplicationInfo;
use Adyen\Model\Checkout\PaymentCaptureRequest;
use Adyen\Model\Checkout\PaymentCaptureResponse;
use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout;
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

    protected function setUp(): void
    {
        $this->adyenHelper = $this->createMock(Data::class);
        $adyenLogger = $this->createMock(AdyenLogger::class);
        $this->idempotencyHelper = $this->createMock(Idempotency::class);

        $this->transactionCapture = new TransactionCapture(
            $this->adyenHelper,
            $adyenLogger,
            $this->idempotencyHelper
        );

        $applicationInfo = $this->createMock(ApplicationInfo::class);
        $this->adyenHelper->method('buildApplicationInfo')->willReturn($applicationInfo);

        $this->request = [
            'amount' => ['value' => 100, 'currency' => 'USD'],
            'paymentPspReference' => 'testPspReference',
            'applicationInfo' => $applicationInfo,
            'idempotencyExtraData' => ['someData']
        ];

        $this->transferObject = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $this->request,
            'getHeaders' => [],
            'getClientConfig' => []
        ]);
    }

    private function configureAdyenMocks(array $response = null, \Exception $exception = null): void
    {
        $adyenClient = $this->createMock(Client::class);
        $checkoutModificationsService = $this->createMock(Checkout\ModificationsApi::class);
        $expectedIdempotencyKey = 'generated_idempotency_key';

        $this->adyenHelper->method('initializeAdyenClientWithClientConfig')->willReturn($adyenClient);
        $this->adyenHelper->method('initializeModificationsApi')->willReturn($checkoutModificationsService);
        $this->adyenHelper->method('buildRequestHeaders')->willReturn([]);
        $this->adyenHelper->expects($this->once())->method('logRequest');

        $trimmedRequest = $this->request;
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

            $request = new PaymentCaptureRequest($this->request);

            $responseMock = $this->createMock(PaymentCaptureResponse::class);
            $responseMock->method('toArray')->willReturn($response);

            $requestOptions['idempotencyKey'] = $expectedIdempotencyKey;
            $requestOptions['headers'] = [];

            $checkoutModificationsService->expects($this->once())
                ->method('captureAuthorisedPayment')
                ->with(
                    $this->request['paymentPspReference'],
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
            'capture_amount' => $this->request['amount']['value'],
            'paymentPspReference' => $this->request['paymentPspReference'],
            'applicationInfo' => $this->request['applicationInfo'],
        ];

        $this->configureAdyenMocks($expectedResponse);

        // Call the method under test
        $response = $this->transactionCapture->placeRequest($this->transferObject);

        // Assert that the response is as expected
        $this->assertEquals($expectedResponse, $response);
    }

    public function testPlaceRequestWithException()
    {
        $expectedException = new AdyenException('Test exception');

        $this->configureAdyenMocks(null, $expectedException);

        // Call the method under test
        $response = $this->transactionCapture->placeRequest($this->transferObject);

        // Assert that the response contains the error message
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Test exception', $response['error']);
    }
}
