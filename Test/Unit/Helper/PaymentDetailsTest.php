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

use Adyen\Payment\Helper\PaymentsDetails;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\Idempotency;
use Magento\Checkout\Model\Session;
use Adyen\Service\Checkout;
use Adyen\Client;

class PaymentDetailsTest extends AbstractAdyenTestCase
{
    private $checkoutSessionMock;
    private $adyenHelperMock;
    private $adyenLoggerMock;
    private $idempotencyHelperMock;
    private $paymentDetails;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);

        $this->paymentDetails = new PaymentsDetails(
            $this->checkoutSessionMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->idempotencyHelperMock
        );
    }

    public function testRequestHeadersAreAddedToRequest()
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $checkoutServiceMock = $this->createMock(Checkout::class);
        $adyenClientMock = $this->createMock(Client::class);
        $storeId = 1;
        $payload = [
            'details' => [
                'detail_key1' => 'some-details'
            ],
            'paymentData' => 'some_payment_data',
            'threeDSAuthenticationOnly' => true
        ];
        $requestOptions = [
        'idempotencyKey' => 'some_idempotency_key',
        'headers' => ['headerKey' => 'headerValue']
        ];
        $paymentDetailsResult = ['resultCode' => 'Authorised', 'action' => null, 'additionalData' => null];

        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($adyenClientMock);
        $this->adyenHelperMock->method('createAdyenCheckoutService')->willReturn($checkoutServiceMock);
        $this->adyenHelperMock->method('buildRequestHeaders')->willReturn($requestOptions['headers']);
        $this->idempotencyHelperMock->method('generateIdempotencyKey')->willReturn($requestOptions['idempotencyKey']);

        $checkoutServiceMock->expects($this->once())
            ->method('paymentsDetails')
            ->with(
                $this->equalTo($payload),
                $this->equalTo($requestOptions)
            )
            ->willReturn($paymentDetailsResult);

        $result = $this->paymentDetails->initiatePaymentDetails($orderMock, $payload);

        $this->assertIsArray($result);
        $this->assertEquals($paymentDetailsResult, $result);
    }
}
