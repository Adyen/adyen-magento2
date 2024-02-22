<?php

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\Client;
use Adyen\Model\Checkout\PaymentCaptureResponse;
use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout\ModificationsApi;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionCaptureTest extends AbstractAdyenTestCase
{
    private $adyenHelperMock;
    private $adyenLoggerMock;
    private $idempotencyHelperMock;
    private $transferObjectMock;
    private $checkoutServiceMock;
    private $clientMock;
    private $transactionCapture;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);
        $this->transferObjectMock = $this->createMock(TransferInterface::class);
        $this->clientMock = $this->createMock(Client::class);
        $this->checkoutServiceMock = $this->createConfiguredMock(ModificationsApi::class, [
            'captureAuthorisedPayment' => new PaymentCaptureResponse([])
        ]);


        $this->transactionCapture = new TransactionCapture(
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->idempotencyHelperMock
        );
    }

    public function testPlaceRequestWithSingleAuthorization()
    {
        // Arrange
        $requestBody = [
            'amount' => ['value' => 1000, 'currency' => 'EUR'],
            'reference' => 'ORDER_REFERENCE',
            TransactionCapture::ORIGINAL_REFERENCE => 'PSP_REFERENCE'
        ];
        $this->transferObjectMock->method('getBody')->willReturn($requestBody);
        $this->transferObjectMock->method('getHeaders')->willReturn([]);
        $this->transferObjectMock->method('getClientConfig')->willReturn([]);

        $this->adyenHelperMock->method('initializeAdyenClientWithClientConfig')->willReturn($this->clientMock);
        $this->adyenHelperMock->method('initializeModificationsApi')->willReturn($this->checkoutServiceMock);
        $this->adyenHelperMock->method('buildRequestHeaders')->willReturn(['x-api-key' => 'test_key']);

        $expectedResult = ['capture_amount' => 1000, 'paymentPspReference' => 'PSP_REFERENCE'];

        // Act
        $result = $this->transactionCapture->placeRequest($this->transferObjectMock);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testPlaceRequestWithMultipleAuthorizations()
    {
        // Arrange
        $multipleAuthorizations = [
            [
                'amount' => ['value' => 500, 'currency' => 'EUR'],
                'reference' => 'ORDER_REFERENCE_1',
                TransactionCapture::ORIGINAL_REFERENCE => 'PSP_REFERENCE_1'
            ],
            [
                'amount' => ['value' => 1500, 'currency' => 'EUR'],
                'reference' => 'ORDER_REFERENCE_2',
                TransactionCapture::ORIGINAL_REFERENCE => 'PSP_REFERENCE_2'
            ]
        ];
        $requestBody = [
            TransactionCapture::MULTIPLE_AUTHORIZATIONS => $multipleAuthorizations,
            Requests::MERCHANT_ACCOUNT => 'MERCHANT_ACCOUNT'
        ];
        $this->transferObjectMock->method('getBody')->willReturn($requestBody);
        $this->transferObjectMock->method('getHeaders')->willReturn([]);
        $this->transferObjectMock->method('getClientConfig')->willReturn([]);

        $this->adyenHelperMock->method('initializeAdyenClientWithClientConfig')->willReturn($this->clientMock);
        $this->adyenHelperMock->method('initializeModificationsApi')->willReturn($this->checkoutServiceMock);
        $this->adyenHelperMock->method('buildRequestHeaders')->willReturn(['x-api-key' => 'test_key']);

        $expectedResults = [
            TransactionCapture::MULTIPLE_AUTHORIZATIONS => [
                [
                    'formatted_capture_amount' => 'EUR ',
                    'capture_amount' => 500,
                    'paymentPspReference' => 'PSP_REFERENCE_1'

                ],
                [
                    'formatted_capture_amount' => 'EUR ',
                    'capture_amount' => 1500,
                    'paymentPspReference' => 'PSP_REFERENCE_2'
                ]
            ]
        ];

        // Act
        $results = $this->transactionCapture->placeRequest($this->transferObjectMock);

        // Assert
        $this->assertEquals($expectedResults, $results);
    }
}