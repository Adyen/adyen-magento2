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

use Adyen\Model\Checkout\ApplicationInfo;
use Adyen\Model\Checkout\PaymentRequest;
use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout\PaymentsApi;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\PaymentResponseFactory;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as PaymentResponseResourceModel;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Gateway\Http\Client\TransactionPayment;
use Magento\Store\Api\Data\StoreInterface;
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

        $this->applicationInfoMock = $this->createMock(ApplicationInfo::class);
        $this->adyenHelperMock->method('buildApplicationInfo')->willReturn($this->applicationInfoMock);

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
        $requestBody = ['reference' => 'ABC12345', 'amount' => ['value' => 100], 'applicationInfo' => $this->applicationInfoMock];
        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $requestBody,
            'getHeaders' => ['idempotencyExtraData' => ['someData']],
            'getClientConfig' => []
        ]);

        $expectedIdempotencyKey = 'generated_idempotency_key';
        $this->idempotencyHelperMock->expects($this->once())
            ->method('generateIdempotencyKey')
            ->with(
                $this->equalTo(['reference' => 'ABC12345', 'amount' => ['value' => 100], 'applicationInfo' => $this->applicationInfoMock]),
                $this->equalTo(['someData'])
            )
            ->willReturn($expectedIdempotencyKey);

        $paymentResponse = new \Adyen\Model\Checkout\PaymentResponse([
            'reference' => 'ABC12345',
            'amount' => ['value' => 100],
            'resultCode' => 'Authorised'
        ]);
        $serviceMock = $this->createMock(PaymentsApi::class);
        $serviceMock->expects($this->once())
            ->method('payments')
            ->with(
                $this->anything(),
                $this->callback(function ($requestOptions) use ($expectedIdempotencyKey) {
                    return isset($requestOptions['idempotencyKey']) &&
                        $requestOptions['idempotencyKey'] === $expectedIdempotencyKey;
                })
            )
            ->willReturn($paymentResponse);

        $this->adyenHelperMock->method('initializePaymentsApi')->willReturn($serviceMock);

        $response = $this->transactionPayment->placeRequest($transferObjectMock);

        $this->assertArrayHasKey('resultCode', $response);
        $this->assertEquals('Authorised', $response['resultCode']);
    }

    public function testRequestHeadersAreAddedToPaymentsCall()
    {
        $requestBody = new PaymentRequest(['reference' => 'ABC12345', 'amount' => ['value' => 1000], 'applicationInfo' => $this->applicationInfoMock]);
        $expectedHeaders = ['header1' => 'value1', 'header2' => 'value2'];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => ['reference' => 'ABC12345', 'amount' => ['value' => 1000], 'applicationInfo' => $this->applicationInfoMock],
            'getHeaders' => ['header1' => 'value1', 'header2' => 'value2'],
            'getClientConfig' => []
        ]);

        $this->adyenHelperMock->expects($this->once())
            ->method('buildRequestHeaders')
            ->willReturn($expectedHeaders);

        $actualHeaders = $this->adyenHelperMock->buildRequestHeaders();

        $paymentResponse = new \Adyen\Model\Checkout\PaymentResponse([
            'reference' => 'ABC12345',
            'amount' => ['value' => 100],
            'resultCode' => 'Authorised'
        ]);

        $serviceMock = $this->createMock(PaymentsApi::class);
        $serviceMock->expects($this->once())
            ->method('payments')
            ->with(
                $this->equalTo($requestBody),
                $this->callback(function ($requestOptions) use ($expectedHeaders) {
                    return isset($requestOptions['headers']) && $requestOptions['headers'] === $expectedHeaders;
                })
            )
            ->willReturn($paymentResponse);

        $this->adyenHelperMock->method('initializePaymentsApi')->willReturn($serviceMock);

        $response = $this->transactionPayment->placeRequest($transferObjectMock);

        $this->assertArrayHasKey('resultCode', $response);
        $this->assertEquals('Authorised', $response['resultCode']);
        $this->assertEquals($expectedHeaders, $actualHeaders);
    }

    public function testProcessGiftCardsWithNoGiftCards()
    {
        $originalRequest = ['amount' => ['value' => 150, 'currency' => 'EUR']];
        $service = $this->createMock(PaymentsApi::class);
        list($request, $giftcardResponse) = $this->transactionPayment->processGiftcards($originalRequest, $service);

        $this->assertEquals($request, $originalRequest);
        $this->assertNull($giftcardResponse);
    }

    public function testProcessGiftCardsWithGiftCards()
    {
        $amount = 250;
        $store = $this->createConfiguredMock(StoreInterface::class, [
            'getId' => 12
        ]);
        $this->storeManagerMock->method('getStore')->willReturn($store);
        $originalRequest = [
            'reference' => '0000020',
            'giftcardRequestParameters' => [
                [
                    'state_data' => '{"paymentMethod":{"type": "giftcard"}, "giftcard": {"balance": {"value": 100}, "currency": "EUR"}}'],
                [
                    'state_data' => '{"paymentMethod":{"type": "giftcard"}, "giftcard": {"balance": {"value": 50}, "currency": "EUR"}}'
                ]
            ],
            'amount' => [
                'value' => $amount,
                'currency' => 'EUR'
            ]
        ];
        $response = new \Adyen\Model\Checkout\PaymentResponse();
        $response->setResultCode('Authorised');
        $response->setMerchantReference('PSPDMDM2222');
        $serviceMock = $this->createMock(PaymentsApi::class);
        $serviceMock->expects($this->exactly(2))
            ->method('payments')
            ->with(
                $this->callback(function (PaymentRequest $detailsRequest) {
                    return true;
                }),
            )->willReturn($response);
        $reflector = new \ReflectionProperty(TransactionPayment::class, 'remainingOrderAmount');
        $reflector->setAccessible(true);
        $reflector->setValue($this->transactionPayment, $amount);
        $orderData = [
            'pspReference' => 'pspReference!23',
            'orderData' => 'orderData....'
        ];
        $this->orderApiHelperMock
            ->expects($this->once())
            ->method('createOrder')
            ->willReturn($orderData);

        list($request, $giftCardResponse) = $this->transactionPayment->processGiftcards($originalRequest, $serviceMock);
        $this->assertEquals(
            $request,
            [
                'reference' => '0000020',
                'amount' => [
                    'value' => 100,
                    'currency' => 'EUR'
                ],
                'order' => $orderData
            ]
        );
    }
}
