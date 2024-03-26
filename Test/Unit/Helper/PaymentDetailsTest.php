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

use Adyen\AdyenException;
use Adyen\Payment\Helper\PaymentsDetails;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
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

    private $orderMock;
    private $paymentMock;
    private $checkoutServiceMock;
    private $adyenClientMock;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);

        $this->orderMock = $this->createMock(OrderInterface::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->checkoutServiceMock = $this->createMock(Checkout::class);
        $this->adyenClientMock = $this->createMock(Client::class);

        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStoreId')->willReturn(1);
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);

        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($this->adyenClientMock);
        $this->adyenHelperMock->method('createAdyenCheckoutService')->willReturn($this->checkoutServiceMock);

        $this->paymentDetails = new PaymentsDetails(
            $this->checkoutSessionMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->idempotencyHelperMock
        );
    }

    public function testInitiatePaymentDetailsSuccessfully()
    {
        $payload = [
            'details' => [
                'detail_key1' => 'some-details',
                'merchantReference' => '00000000001'
            ],
            'paymentData' => 'some_payment_data',
            'threeDSAuthenticationOnly' => true,
        ];

        $requestOptions = [
            'idempotencyKey' => 'some_idempotency_key',
            'headers' => ['headerKey' => 'headerValue']
        ];

        $paymentDetailsResult = ['resultCode' => 'Authorised', 'action' => null, 'additionalData' => null];

        $this->adyenHelperMock->method('buildRequestHeaders')->willReturn($requestOptions['headers']);
        $this->idempotencyHelperMock->method('generateIdempotencyKey')->willReturn($requestOptions['idempotencyKey']);

        // testing cleanUpPaymentDetailsPayload() method
        $apiPayload = $payload;
        unset($apiPayload['details']['merchantReference']);

        $this->checkoutServiceMock->expects($this->once())
            ->method('paymentsDetails')
            ->with($apiPayload, $requestOptions)
            ->willReturn($paymentDetailsResult);

        $result = $this->paymentDetails->initiatePaymentDetails($this->orderMock, $payload);

        $this->assertIsArray($result);
        $this->assertEquals($paymentDetailsResult, $result);
    }

    public function testInitiatePaymentDetailsFailure()
    {
        $this->expectException(ValidatorException::class);

        $payload = [
            'details' => [
                'detail_key1' => 'some-details',
                'merchantReference' => '00000000001'
            ],
            'paymentData' => 'some_payment_data',
            'threeDSAuthenticationOnly' => true,
        ];

        $this->checkoutServiceMock->method('paymentsDetails')->willThrowException(new AdyenException());

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');
        $this->checkoutSessionMock->expects($this->atLeastOnce())->method('restoreQuote');

        $this->paymentDetails->initiatePaymentDetails($this->orderMock, $payload);
    }
}
