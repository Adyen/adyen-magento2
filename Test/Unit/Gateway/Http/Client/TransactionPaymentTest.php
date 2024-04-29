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

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\PaymentResponseFactory;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as PaymentResponseResourceModel;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Gateway\Http\Client\TransactionPayment;
use Magento\Store\Model\StoreManagerInterface;
use Adyen\Payment\Helper\GiftcardPayment;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPaymentTest extends AbstractAdyenTestCase
{
    private $adyenHelperMock;
    private $paymentResponseFactoryMock;
    private $paymentResponseResourceModelMock;
    private $idempotencyHelperMock;
    private $orderApiHelperMock;
    private $storeManagerMock;
    private $giftcardPaymentHelperMock;
    private $transactionPayment;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->paymentResponseResourceModelMock = $this->createMock(PaymentResponseResourceModel::class);
        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);
        $this->orderApiHelperMock = $this->createMock(OrdersApi::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->giftcardPaymentHelperMock = $this->createMock(GiftcardPayment::class);
        $paymentResponseInterfaceMock = $this->createMock(PaymentResponseInterface::class);
        $paymentResponseMock = $this->createMock(PaymentResponse::class);
        $paymentResponseMock->method('setResponse')->willReturn($paymentResponseInterfaceMock);
        $paymentResponseMock->method('setResultCode')->willReturn($paymentResponseInterfaceMock);
        $paymentResponseMock->method('setMerchantReference')->willReturn($paymentResponseInterfaceMock);
        $this->paymentResponseFactoryMock = $this->createGeneratedMock(PaymentResponseFactory::class, ['create']);
        $this->paymentResponseFactoryMock->method('create')->willReturn($paymentResponseMock);

        $this->transactionPayment = $objectManager->getObject(
            TransactionPayment::class,
            [
                'adyenHelper' => $this->adyenHelperMock,
                'paymentResponseFactory' => $this->paymentResponseFactoryMock,
                'paymentResponseResourceModel' => $this->paymentResponseResourceModelMock,
                'idempotencyHelper' => $this->idempotencyHelperMock,
                'orderApiHelper' => $this->orderApiHelperMock,
                'storeManager' => $this->storeManagerMock,
                'giftcardPaymentHelper' => $this->giftcardPaymentHelperMock,
            ]
        );
    }

    public function testPlaceRequestWithResultCode()
    {
        $transferObjectMock = $this->createMock(TransferInterface::class);
        $requestBody = ['resultCode' => 'Authorised', 'amount' => ['value' => 1000]];
        $transferObjectMock->method('getBody')->willReturn($requestBody);

        $result = $this->transactionPayment->placeRequest($transferObjectMock);

        $this->assertEquals($requestBody, $result);
    }

    public function testPlaceRequestGeneratesIdempotencyKey()
    {
        $requestBody = ['reference' => 'ABC12345', 'amount' => ['value' => 100]];
        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $requestBody,
            'getHeaders' => ['idempotencyExtraData' => ['someData']],
            'getClientConfig' => []
        ]);

        $expectedIdempotencyKey = 'generated_idempotency_key';
        $this->idempotencyHelperMock->expects($this->once())
            ->method('generateIdempotencyKey')
            ->with(
                $this->equalTo(['reference' => 'ABC12345', 'amount' => ['value' => 100]]),
                $this->equalTo(['someData'])
            )
            ->willReturn($expectedIdempotencyKey);

        $mockedPaymentResponse = [
            'reference' => 'ABC12345',
            'amount' => ['value' => 100],
            'resultCode' => 'Authorised'
        ];
        $serviceMock = $this->createMock(Checkout::class);
        $serviceMock->expects($this->once())
            ->method('payments')
            ->with(
                $this->anything(),
                $this->callback(function ($requestOptions) use ($expectedIdempotencyKey) {
                    return isset($requestOptions['idempotencyKey']) &&
                        $requestOptions['idempotencyKey'] === $expectedIdempotencyKey;
                })
            )
            ->willReturn($mockedPaymentResponse);

        $this->adyenHelperMock->method('createAdyenCheckoutService')->willReturn($serviceMock);

        $response = $this->transactionPayment->placeRequest($transferObjectMock);

        $this->assertArrayHasKey('resultCode', $response);
        $this->assertEquals('Authorised', $response['resultCode']);
    }

    public function testRequestHeadersAreAddedToPaymentsCall()
    {
        $requestBody = ['reference' => 'ABC12345', 'amount' => ['value' => 1000]];
        $expectedHeaders = ['header1' => 'value1', 'header2' => 'value2'];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => ['reference' => 'ABC12345', 'amount' => ['value' => 1000]],
            'getHeaders' => ['header1' => 'value1', 'header2' => 'value2'],
            'getClientConfig' => []
        ]);

        $this->adyenHelperMock->expects($this->once())
            ->method('buildRequestHeaders')
            ->willReturn($expectedHeaders);

        $actualHeaders = $this->adyenHelperMock->buildRequestHeaders();


        $mockedPaymentResponse = [
            'reference' => 'ABC12345',
            'amount' => ['value' => 100],
            'resultCode' => 'Authorised'
        ];

        $serviceMock = $this->createMock(Checkout::class);
        $serviceMock->expects($this->once())
            ->method('payments')
            ->with(
                $this->equalTo($requestBody),
                $this->callback(function ($requestOptions) use ($expectedHeaders) {
                    return isset($requestOptions['headers']) && $requestOptions['headers'] === $expectedHeaders;
                })
            )
            ->willReturn($mockedPaymentResponse);

        $this->adyenHelperMock->method('createAdyenCheckoutService')->willReturn($serviceMock);

        $response = $this->transactionPayment->placeRequest($transferObjectMock);

        $this->assertArrayHasKey('resultCode', $response);
        $this->assertEquals('Authorised', $response['resultCode']);
        $this->assertEquals($expectedHeaders, $actualHeaders);
    }
}
