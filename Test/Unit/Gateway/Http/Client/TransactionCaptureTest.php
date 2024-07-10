<?php

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout;
use Magento\Payment\Gateway\Http\TransferInterface;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Data;
use Adyen\AdyenException;
use PHPUnit\Framework\MockObject\MockObject;

class TransactionCaptureTest extends AbstractAdyenTestCase
{
    private TransactionCapture $transactionCapture;
    private TransferInterface|MockObject $transferObject;
    private array $request;
    private Data|MockObject $adyenHelper;
    private Idempotency|MockObject $idempotencyHelper;

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

        $this->request = [
            'amount' => ['value' => 100, 'currency' => 'USD'],
            'paymentPspReference' => 'testPspReference'
        ];

        $this->transferObject = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $this->request,
            'getHeaders' => ['idempotencyExtraData' => ['someData']],
            'getClientConfig' => []
        ]);
    }

    private function configureAdyenMocks(array $response = null, \Exception $exception = null)
    {
        $adyenClient = $this->createMock(\Adyen\Client::class);
        $adyenService = $this->createMock(Checkout::class);
        $expectedIdempotencyKey = 'generated_idempotency_key';

        $this->adyenHelper->method('initializeAdyenClientWithClientConfig')->willReturn($adyenClient);
        $this->adyenHelper->method('createAdyenCheckoutService')->willReturn($adyenService);
        $this->adyenHelper->method('buildRequestHeaders')->willReturn([]);
        $this->adyenHelper->expects($this->once())->method('logRequest');
        $this->adyenHelper->expects($this->once())->method('logResponse');

        $this->idempotencyHelper->expects($this->once())
            ->method('generateIdempotencyKey')
            ->with(
                $this->request,
                $this->equalTo(['someData'])
            )
            ->willReturn($expectedIdempotencyKey);

        if ($response) {
            $adyenService->expects($this->once())
                ->method('captureAuthorisedPayment')
                ->with(
                    $this->equalTo($this->request),
                    $this->callback(function ($requestOptions) use ($expectedIdempotencyKey) {
                        return isset($requestOptions['idempotencyKey']) &&
                            $requestOptions['idempotencyKey'] === $expectedIdempotencyKey;
                    })
                )
                ->willReturn($response);
        }

        if ($exception) {
            $adyenService->expects($this->once())
                ->method('captureAuthorisedPayment')
                ->with(
                    $this->equalTo($this->request),
                    $this->callback(function ($requestOptions) use ($expectedIdempotencyKey) {
                        return isset($requestOptions['idempotencyKey']) &&
                            $requestOptions['idempotencyKey'] === $expectedIdempotencyKey;
                    })
                )
                ->willThrowException($exception);
        }

        return $adyenService;
    }

    public function testPlaceRequest()
    {
        $expectedResponse = [
            'capture_amount' => $this->request['amount']['value'],
            'paymentPspReference' => $this->request['paymentPspReference']
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
