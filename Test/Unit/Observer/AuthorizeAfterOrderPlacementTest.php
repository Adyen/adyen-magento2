<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AuthorizationHandler;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection as AdyenPaymentResponseCollection;
use Adyen\Payment\Observer\AuthorizeAfterOrderPlacement;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AuthorizeAfterOrderPlacementTest extends AbstractAdyenTestCase
{
    private AuthorizeAfterOrderPlacement $authorizeAfterOrderPlacement;
    private AuthorizationHandler|MockObject $authorizationHandlerMock;
    private AdyenPaymentResponseCollection|MockObject $adyenPaymentResponseCollectionMock;
    private PaymentMethods|MockObject $paymentMethodsMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private OrderRepositoryInterface|MockObject $orderRepositoryMock;
    private Observer|MockObject $observerMock;
    private Order|MockObject $orderMock;
    private Payment|MockObject $paymentMock;

    private const INCREMENT_ID = '000000001';
    private const PSP_REFERENCE = 'ABCD1234567890';
    private const AMOUNT_VALUE = 1000;
    private const AMOUNT_CURRENCY = 'EUR';
    private const PAYMENT_BRAND = 'visa';

    protected function setUp(): void
    {
        $this->authorizationHandlerMock = $this->createMock(AuthorizationHandler::class);
        $this->adyenPaymentResponseCollectionMock = $this->createMock(AdyenPaymentResponseCollection::class);
        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);

        $this->paymentMock = $this->createMock(Payment::class);

        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getIncrementId')->willReturn(self::INCREMENT_ID);

        $this->observerMock = $this->createMock(Observer::class);
        $this->observerMock->method('getData')->with('order')->willReturn($this->orderMock);

        $this->authorizeAfterOrderPlacement = new AuthorizeAfterOrderPlacement(
            $this->authorizationHandlerMock,
            $this->adyenPaymentResponseCollectionMock,
            $this->paymentMethodsMock,
            $this->adyenLoggerMock,
            $this->orderRepositoryMock
        );
    }

    private function buildPaymentResponse(string $resultCode, array $responseData): array
    {
        return [
            PaymentResponseInterface::RESULT_CODE => $resultCode,
            'response' => json_encode($responseData)
        ];
    }

    private function buildAuthorisedResponseData(array $additionalData = []): array
    {
        return [
            'paymentMethod' => ['brand' => self::PAYMENT_BRAND],
            'pspReference' => self::PSP_REFERENCE,
            'amount' => [
                'value' => self::AMOUNT_VALUE,
                'currency' => self::AMOUNT_CURRENCY
            ],
            'additionalData' => $additionalData
        ];
    }

    public function testNonAdyenPaymentReturnsEarly(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('checkmo');
        $this->paymentMethodsMock->method('isAdyenPayment')->with('checkmo')->willReturn(false);

        $this->adyenPaymentResponseCollectionMock
            ->expects($this->never())
            ->method('getPaymentResponsesWithMerchantReferences');

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }

    public function testNoPaymentResponses(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMethodsMock->method('isAdyenPayment')->with('adyen_cc')->willReturn(true);

        $this->adyenPaymentResponseCollectionMock
            ->method('getPaymentResponsesWithMerchantReferences')
            ->with([self::INCREMENT_ID])
            ->willReturn([]);

        $this->authorizationHandlerMock->expects($this->never())->method('execute');
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }

    public function testNonAuthorisedResponseIsSkipped(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMethodsMock->method('isAdyenPayment')->willReturn(true);

        $paymentResponse = $this->buildPaymentResponse('Refused', []);

        $this->adyenPaymentResponseCollectionMock
            ->method('getPaymentResponsesWithMerchantReferences')
            ->willReturn([$paymentResponse]);

        $this->authorizationHandlerMock->expects($this->never())->method('execute');
        $this->orderRepositoryMock->expects($this->never())->method('save');

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }

    public function testAuthorisedResponseProcessesAuthorization(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMethodsMock->method('isAdyenPayment')->willReturn(true);

        $additionalData = ['someKey' => 'someValue'];
        $responseData = $this->buildAuthorisedResponseData($additionalData);
        $paymentResponse = $this->buildPaymentResponse(PaymentResponseHandler::AUTHORISED, $responseData);

        $this->adyenPaymentResponseCollectionMock
            ->method('getPaymentResponsesWithMerchantReferences')
            ->with([self::INCREMENT_ID])
            ->willReturn([$paymentResponse]);

        $this->authorizationHandlerMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->orderMock,
                self::PAYMENT_BRAND,
                self::PSP_REFERENCE,
                self::AMOUNT_VALUE,
                self::AMOUNT_CURRENCY,
                $additionalData
            )
            ->willReturn($this->orderMock);

        $this->orderRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->orderMock);

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }

    public function testMultipleResponsesOnlyProcessesAuthorised(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMethodsMock->method('isAdyenPayment')->willReturn(true);

        $responseData = $this->buildAuthorisedResponseData();
        $authorisedResponse = $this->buildPaymentResponse(PaymentResponseHandler::AUTHORISED, $responseData);
        $refusedResponse = $this->buildPaymentResponse('Refused', []);
        $pendingResponse = $this->buildPaymentResponse('Pending', []);

        $this->adyenPaymentResponseCollectionMock
            ->method('getPaymentResponsesWithMerchantReferences')
            ->willReturn([$refusedResponse, $authorisedResponse, $pendingResponse]);

        $this->authorizationHandlerMock->expects($this->once())
            ->method('execute')
            ->willReturn($this->orderMock);

        $this->orderRepositoryMock->expects($this->once())->method('save');

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }

    public function testMultipleAuthorisedResponsesAllProcessed(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMethodsMock->method('isAdyenPayment')->willReturn(true);

        $responseData1 = $this->buildAuthorisedResponseData();
        $responseData2 = $this->buildAuthorisedResponseData(['key' => 'val']);
        $authorisedResponse1 = $this->buildPaymentResponse(PaymentResponseHandler::AUTHORISED, $responseData1);
        $authorisedResponse2 = $this->buildPaymentResponse(PaymentResponseHandler::AUTHORISED, $responseData2);

        $this->adyenPaymentResponseCollectionMock
            ->method('getPaymentResponsesWithMerchantReferences')
            ->willReturn([$authorisedResponse1, $authorisedResponse2]);

        $this->authorizationHandlerMock->expects($this->exactly(2))
            ->method('execute')
            ->willReturn($this->orderMock);

        $this->orderRepositoryMock->expects($this->exactly(2))->method('save');

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }

    public function testExceptionIsLoggedAndNotThrown(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMethodsMock->method('isAdyenPayment')->willReturn(true);

        $responseData = $this->buildAuthorisedResponseData();
        $paymentResponse = $this->buildPaymentResponse(PaymentResponseHandler::AUTHORISED, $responseData);

        $this->adyenPaymentResponseCollectionMock
            ->method('getPaymentResponsesWithMerchantReferences')
            ->willReturn([$paymentResponse]);

        $exceptionMessage = 'Something went wrong';
        $this->authorizationHandlerMock->method('execute')
            ->willThrowException(new Exception($exceptionMessage));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with(sprintf(
                'Failed to process authorization after order placement for order #%s: %s',
                self::INCREMENT_ID,
                $exceptionMessage
            ));

        $this->orderRepositoryMock->expects($this->never())->method('save');

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }

    public function testExceptionDuringOrderSaveIsLogged(): void
    {
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMethodsMock->method('isAdyenPayment')->willReturn(true);

        $responseData = $this->buildAuthorisedResponseData();
        $paymentResponse = $this->buildPaymentResponse(PaymentResponseHandler::AUTHORISED, $responseData);

        $this->adyenPaymentResponseCollectionMock
            ->method('getPaymentResponsesWithMerchantReferences')
            ->willReturn([$paymentResponse]);

        $this->authorizationHandlerMock->method('execute')->willReturn($this->orderMock);

        $exceptionMessage = 'Could not save order';
        $this->orderRepositoryMock->method('save')
            ->willThrowException(new Exception($exceptionMessage));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with(sprintf(
                'Failed to process authorization after order placement for order #%s: %s',
                self::INCREMENT_ID,
                $exceptionMessage
            ));

        $this->authorizeAfterOrderPlacement->execute($this->observerMock);
    }
}
