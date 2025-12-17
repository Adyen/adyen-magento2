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

use Adyen\Client;
use Adyen\Model\Checkout\PaymentRefundRequest;
use Adyen\Model\Checkout\PaymentRefundResponse;
use Adyen\Payment\Gateway\Http\Client\TransactionRefund;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout\ModificationsApi;
use Magento\Payment\Gateway\Http\TransferInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\AdyenException;

class TransactionRefundTest extends AbstractAdyenTestCase
{
    /**
     * @var Data|MockObject
     */
    private $adyenHelperMock;

    /**
     * @var Idempotency|MockObject
     */
    private $idempotencyHelperMock;

    /**
     * @var TransactionRefund
     */
    private $transactionRefund;

    private PlatformInfo $platformInfo;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);
        $this->platformInfo = $this->createMock(PlatformInfo::class);

        $this->transactionRefund = new TransactionRefund(
            $this->adyenHelperMock,
            $this->idempotencyHelperMock,
            $this->platformInfo
        );
    }

    public function testPlaceRequestIncludesHeadersInRequest()
    {
        $requestBody = [
            'amount' => ['value' => 1000, 'currency' => 'EUR'],
            'paymentPspReference' => '123456789'
        ];

        $headers = ['idempotencyExtraData' => ['order_id' => '1001']];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => [$requestBody],
            'getHeaders' => $headers,
            'getClientConfig' => []
        ]);

        $serviceMock = $this->createMock(ModificationsApi::class);
        $adyenClientMock = $this->createMock(Client::class);

        $this->adyenHelperMock->method('initializeAdyenClientWithClientConfig')->willReturn($adyenClientMock);
        $this->adyenHelperMock->method('initializeModificationsApi')->willReturn($serviceMock);

        $this->idempotencyHelperMock->expects($this->once())
            ->method('generateIdempotencyKey')
            ->with($requestBody, $headers['idempotencyExtraData'])
            ->willReturn('generated_idempotency_key');

        $serviceMock->expects($this->once())
            ->method('refundCapturedPayment')
            ->with(
                $this->equalTo($requestBody['paymentPspReference']),
                $this->callback(function (PaymentRefundRequest $paymentRefundRequest) {
                    $amount = $paymentRefundRequest->getAmount();
                    $this->assertEquals($amount,['value' => 1000, 'currency' => 'EUR']);
                    return true;
                }),
                $this->callback(function ($requestOptions) {
                    $this->assertArrayHasKey('idempotencyKey', $requestOptions);
                    $this->assertArrayHasKey('headers', $requestOptions);
                    $this->assertEquals('generated_idempotency_key', $requestOptions['idempotencyKey']);
                    return true;
                })
            )
            ->willReturn(new PaymentRefundResponse(['pspReference' => 'refund_psp_reference']));

        $responses = $this->transactionRefund->placeRequest($transferObjectMock);

        $this->assertIsArray($responses);
        $this->assertCount(1, $responses);
        $this->assertArrayHasKey('pspReference', $responses[0]);
    }

    public function testPlaceRequestHandlesException()
    {
        $requestBody = [
            'amount' => ['value' => 1000, 'currency' => 'EUR'],
            'paymentPspReference' => '123456789'
        ];

        $headers = ['idempotencyExtraData' => ['order_id' => '1001']];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => [$requestBody],
            'getHeaders' => $headers,
            'getClientConfig' => []
        ]);

        $serviceMock = $this->createMock(ModificationsApi::class);
        $adyenClientMock = $this->createMock(Client::class);

        $this->adyenHelperMock->method('initializeAdyenClientWithClientConfig')->willReturn($adyenClientMock);
        $this->adyenHelperMock->method('initializeModificationsApi')->willReturn($serviceMock);

        $this->idempotencyHelperMock->expects($this->once())
            ->method('generateIdempotencyKey')
            ->with($requestBody, $headers['idempotencyExtraData'])
            ->willReturn('generated_idempotency_key');

        $serviceMock->expects($this->once())
            ->method('refundCapturedPayment')
            ->willThrowException(new AdyenException());

        $this->adyenHelperMock->expects($this->once())
            ->method('logAdyenException')
            ->with($this->isInstanceOf(AdyenException::class));

        $responses = $this->transactionRefund->placeRequest($transferObjectMock);
        $this->assertIsArray($responses);
        $this->assertCount(1, $responses);
        $this->assertArrayHasKey('error', $responses[0]);
        $this->assertArrayHasKey('errorCode', $responses[0]);
    }
}
