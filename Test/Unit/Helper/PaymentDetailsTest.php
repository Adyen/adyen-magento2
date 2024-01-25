<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Model\Checkout\PaymentDetailsRequest;
use Adyen\Model\Checkout\PaymentDetailsResponse;
use Adyen\Payment\Helper\PaymentsDetails;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\Idempotency;
use Magento\Checkout\Model\Session;
use Adyen\Service\Checkout\PaymentsApi;
use Adyen\Client;

class PaymentDetailsTest extends AbstractAdyenTestCase
{
    private $checkoutSessionMock;
    private $adyenHelperMock;
    private $adyenLoggerMock;
    private $paymentResponseHandlerMock;
    private $idempotencyHelperMock;
    private $paymentDetails;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->paymentResponseHandlerMock = $this->createMock(PaymentResponseHandler::class);
        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);

        $this->paymentDetails = new PaymentsDetails(
            $this->checkoutSessionMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->paymentResponseHandlerMock,
            $this->idempotencyHelperMock
            );
    }

    public function testRequestHeadersAreAddedToRequest()
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $serviceMock = $this->createMock(PaymentsApi::class);
        $adyenClientMock = $this->createMock(Client::class);
        $storeId = 1;
        $payload = json_encode([
            'details' => 'some_details',
            'paymentData' => 'some_payment_data',
            'threeDSAuthenticationOnly' => true
        ]);
        $requestOptions = [
        'idempotencyKey' => 'some_idempotency_key',
        'headers' => ['headerKey' => 'headerValue']
        ];
        $paymentDetailsResult = ['resultCode' => 'Authorised', 'action' => null, 'additionalData' => null];

        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($adyenClientMock);
        $this->adyenHelperMock->method('initializePaymentsApi')->willReturn($serviceMock);
        $this->adyenHelperMock->method('buildRequestHeaders')->willReturn($requestOptions['headers']);
        $this->idempotencyHelperMock->method('generateIdempotencyKey')->willReturn($requestOptions['idempotencyKey']);

        $serviceMock->expects($this->once())
            ->method('paymentsDetails')
            ->with(
                $this->callback(function(PaymentDetailsRequest $detailsRequest) {
                    $this->assertEquals(true,  $detailsRequest->getThreeDSAuthenticationOnly());
                    $this->assertEquals('some_payment_data',  $detailsRequest->getPaymentData());
                    return true;
                }),
                $this->equalTo($requestOptions)
            )
            ->willReturn(new PaymentDetailsResponse($paymentDetailsResult));

        $this->paymentResponseHandlerMock->method('handlePaymentResponse')->willReturn(true);
        $this->paymentResponseHandlerMock->method('formatPaymentResponse')->willReturn($paymentDetailsResult);

        $result = $this->paymentDetails->initiatePaymentDetails($orderMock, $payload);

        $this->assertJson($result);
        $this->assertEquals(json_encode($paymentDetailsResult), $result);
    }
}
