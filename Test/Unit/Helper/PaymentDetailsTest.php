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
use Adyen\Model\Checkout\PaymentDetailsRequest;
use Adyen\Model\Checkout\PaymentDetailsResponse;
use Adyen\Payment\Helper\PaymentsDetails;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Idempotency;
use Magento\Checkout\Model\Session;
use Adyen\Service\Checkout\PaymentsApi;
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
    private $paymentsApiMock;
    private $adyenClientMock;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);

        $this->orderMock = $this->createMock(OrderInterface::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->paymentsApiMock = $this->createMock(PaymentsApi::class);
        $this->adyenClientMock = $this->createMock(Client::class);

        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStoreId')->willReturn(1);
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);

        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($this->adyenClientMock);
        $this->adyenHelperMock->method('initializePaymentsApi')->willReturn($this->paymentsApiMock);
        $this->setUpAdyenHelperMockExpectations();

        $this->paymentDetails = new PaymentsDetails(
            $this->checkoutSessionMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->idempotencyHelperMock
        );

    }

    private function setUpAdyenHelperMockExpectations(): void
    {
        $this->adyenHelperMock->expects($this->once())
            ->method('getMagentoDetails')
            ->willReturn(['name' => 'Magento', 'version' => '2.x.x', 'edition' => 'Community']);

        $this->adyenHelperMock->expects($this->once())
            ->method('getModuleName')
            ->willReturn('adyen-magento2');

        $this->adyenHelperMock->expects($this->once())
            ->method('getModuleVersion')
            ->willReturn('1.2.3');
    }

    public function testInitiatePaymentDetailsSuccessfully()
    {
        $serviceMock = $this->createMock(PaymentsApi::class);
        $adyenClientMock = $this->createMock(Client::class);
        $payload = [
            'details' => [
                'some_value' => 'some_details',
                'merchantReference' => '00000000001'
            ],
            'paymentData' => 'some_payment_data',
            'threeDSAuthenticationOnly' => true,
        ];

        $requestOptions = [
            'idempotencyKey' => 'some_idempotency_key',
            'headers' => [
                'external-platform-name' => 'Magento',
                'external-platform-version' => '2.x.x',
                'external-platform-edition' => 'Community',
                'merchant-application-name' => 'adyen-magento2',
                'merchant-application-version' => '1.2.3'
            ]
        ];

        $paymentDetailsResult = ['resultCode' => 'Authorised'];

        $this->idempotencyHelperMock->method('generateIdempotencyKey')->willReturn($requestOptions['idempotencyKey']);

        // testing cleanUpPaymentDetailsPayload() method
        $apiPayload = $payload;
        unset($apiPayload['details']['merchantReference']);

        $this->paymentsApiMock->expects($this->once())
            ->method('paymentsDetails')
            ->with(new PaymentDetailsRequest($apiPayload), $requestOptions)
            ->willReturn(new PaymentDetailsResponse($paymentDetailsResult));

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

        $this->paymentsApiMock->method('paymentsDetails')->willThrowException(new AdyenException());

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');
        $this->checkoutSessionMock->expects($this->atLeastOnce())->method('restoreQuote');

        $this->paymentDetails->initiatePaymentDetails($this->orderMock, $payload);
    }
}
