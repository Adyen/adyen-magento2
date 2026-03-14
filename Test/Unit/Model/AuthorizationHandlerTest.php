<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AuthorizationHandler;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AuthorizationHandlerTest extends AbstractAdyenTestCase
{
    private AuthorizationHandler $authorizationHandler;
    private AdyenOrderPayment|MockObject $adyenOrderPaymentHelperMock;
    private CaseManagement|MockObject $caseManagementHelperMock;
    private Invoice|MockObject $invoiceHelperMock;
    private PaymentMethods|MockObject $paymentMethodsHelperMock;
    private OrderHelper|MockObject $orderHelperMock;
    private AdyenLogger|MockObject $adyenLoggerMock;

    private const PSP_REFERENCE = 'ABCD1234567890';
    private const AMOUNT_VALUE = 1000;
    private const AMOUNT_CURRENCY = 'EUR';
    private const PAYMENT_METHOD = 'adyen_cc';
    private const GRAND_TOTAL = 10.00;
    private const BASE_GRAND_TOTAL = 10.00;

    protected function setUp(): void
    {
        $this->adyenOrderPaymentHelperMock = $this->createMock(AdyenOrderPayment::class);
        $this->caseManagementHelperMock = $this->createMock(CaseManagement::class);
        $this->invoiceHelperMock = $this->createMock(Invoice::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->authorizationHandler = new AuthorizationHandler(
            $this->adyenOrderPaymentHelperMock,
            $this->caseManagementHelperMock,
            $this->invoiceHelperMock,
            $this->paymentMethodsHelperMock,
            $this->orderHelperMock,
            $this->adyenLoggerMock
        );
    }

    private function createOrderMock(bool $emailSent = false): Order|MockObject
    {
        $paymentMock = $this->createMock(Payment::class);

        $orderMock = $this->createMockWithMethods(
            Order::class,
            [
                'getPayment',
                'getGrandTotal',
                'getBaseGrandTotal',
                'getEmailSent',
                'getIncrementId',
                'getStatus',
                'addCommentToStatusHistory',
                'setData'
            ],
            []
        );

        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getGrandTotal')->willReturn(self::GRAND_TOTAL);
        $orderMock->method('getBaseGrandTotal')->willReturn(self::BASE_GRAND_TOTAL);
        $orderMock->method('getEmailSent')->willReturn($emailSent);
        $orderMock->method('getIncrementId')->willReturn('000000001');
        $orderMock->method('getStatus')->willReturn('processing');

        return $orderMock;
    }

    public function testExecutePartialAuthorization()
    {
        $orderMock = $this->createOrderMock();
        $additionalData = [];

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(true);
        $this->adyenOrderPaymentHelperMock->expects($this->once())
            ->method('createAdyenOrderPayment')
            ->with($orderMock, true, self::PSP_REFERENCE, self::PAYMENT_METHOD, self::AMOUNT_VALUE, self::AMOUNT_CURRENCY);

        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(false);

        $this->orderHelperMock->expects($this->never())->method('setPrePaymentAuthorized');
        $this->invoiceHelperMock->expects($this->never())->method('createInvoice');

        $result = $this->authorizationHandler->execute(
            $orderMock,
            self::PAYMENT_METHOD,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            $additionalData
        );

        $this->assertSame($orderMock, $result);
    }

    public function testExecuteFullAuthorizationAutoCapture()
    {
        $orderMock = $this->createOrderMock();
        $additionalData = [];

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(true);

        $this->adyenOrderPaymentHelperMock->expects($this->once())
            ->method('createAdyenOrderPayment');
        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(true);

        $this->orderHelperMock->expects($this->once())
            ->method('setPrePaymentAuthorized')
            ->with($orderMock)
            ->willReturn($orderMock);
        $this->orderHelperMock->expects($this->once())
            ->method('updatePaymentDetails')
            ->with($orderMock, self::PSP_REFERENCE);

        $this->caseManagementHelperMock->method('requiresManualReview')->with($additionalData)->willReturn(false);

        $this->invoiceHelperMock->expects($this->once())
            ->method('createInvoice')
            ->with($orderMock, true, self::PSP_REFERENCE, self::AMOUNT_VALUE);

        $this->orderHelperMock->expects($this->once())
            ->method('finalizeOrder')
            ->with($orderMock, self::PSP_REFERENCE, self::AMOUNT_VALUE)
            ->willReturn($orderMock);

        $this->orderHelperMock->expects($this->once())
            ->method('sendOrderMail')
            ->with($orderMock);

        $orderMock->expects($this->once())
            ->method('setData')
            ->with('adyen_notification_payment_captured', 1);

        $result = $this->authorizationHandler->execute(
            $orderMock,
            self::PAYMENT_METHOD,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            $additionalData
        );

        $this->assertSame($orderMock, $result);
    }

    public function testExecuteFullAuthorizationAutoCaptureWithFraudReview()
    {
        $orderMock = $this->createOrderMock();
        $additionalData = ['fraudManualReview' => 'true'];

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(true);

        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(true);

        $this->orderHelperMock->method('setPrePaymentAuthorized')->willReturn($orderMock);

        $this->caseManagementHelperMock->method('requiresManualReview')
            ->with($additionalData)
            ->willReturn(true);

        $this->invoiceHelperMock->expects($this->once())
            ->method('createInvoice')
            ->with($orderMock, true, self::PSP_REFERENCE, self::AMOUNT_VALUE);

        $this->caseManagementHelperMock->expects($this->once())
            ->method('markCaseAsPendingReview')
            ->with($orderMock, self::PSP_REFERENCE, true)
            ->willReturn($orderMock);

        $this->orderHelperMock->expects($this->never())->method('finalizeOrder');

        $result = $this->authorizationHandler->execute(
            $orderMock,
            self::PAYMENT_METHOD,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            $additionalData
        );

        $this->assertSame($orderMock, $result);
    }

    public function testExecuteFullAuthorizationManualCapture()
    {
        $orderMock = $this->createOrderMock();
        $additionalData = [];

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(false);

        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(true);

        $this->orderHelperMock->method('setPrePaymentAuthorized')->willReturn($orderMock);

        $this->caseManagementHelperMock->method('requiresManualReview')->willReturn(false);

        $this->invoiceHelperMock->expects($this->never())->method('createInvoice');

        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory');

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                'Capture mode is set to Manual',
                [
                    'pspReference' => self::PSP_REFERENCE,
                    'merchantReference' => '000000001'
                ]
            );

        $orderMock->expects($this->never())->method('setData');

        $result = $this->authorizationHandler->execute(
            $orderMock,
            self::PAYMENT_METHOD,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            $additionalData
        );

        $this->assertSame($orderMock, $result);
    }

    public function testExecuteFullAuthorizationManualCaptureWithFraudReview()
    {
        $orderMock = $this->createOrderMock();
        $additionalData = ['fraudManualReview' => 'true'];

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(false);

        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(true);

        $this->orderHelperMock->method('setPrePaymentAuthorized')->willReturn($orderMock);

        $this->caseManagementHelperMock->method('requiresManualReview')->willReturn(true);

        $this->caseManagementHelperMock->expects($this->once())
            ->method('markCaseAsPendingReview')
            ->with($orderMock, self::PSP_REFERENCE)
            ->willReturn($orderMock);

        $this->adyenLoggerMock->expects($this->never())->method('addAdyenNotification');

        $result = $this->authorizationHandler->execute(
            $orderMock,
            self::PAYMENT_METHOD,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            $additionalData
        );

        $this->assertSame($orderMock, $result);
    }

    public function testExecuteBoletoDoesNotSendEmail()
    {
        $orderMock = $this->createOrderMock(false);

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(true);
        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(true);
        $this->orderHelperMock->method('setPrePaymentAuthorized')->willReturn($orderMock);
        $this->caseManagementHelperMock->method('requiresManualReview')->willReturn(false);
        $this->orderHelperMock->method('finalizeOrder')->willReturn($orderMock);

        $this->orderHelperMock->expects($this->never())->method('sendOrderMail');

        $this->authorizationHandler->execute(
            $orderMock,
            'adyen_boleto',
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            []
        );
    }

    public function testExecuteDoesNotSendEmailIfAlreadySent()
    {
        $orderMock = $this->createOrderMock(true);

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(true);
        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(true);
        $this->orderHelperMock->method('setPrePaymentAuthorized')->willReturn($orderMock);
        $this->caseManagementHelperMock->method('requiresManualReview')->willReturn(false);
        $this->orderHelperMock->method('finalizeOrder')->willReturn($orderMock);

        $this->orderHelperMock->expects($this->never())->method('sendOrderMail');

        $this->authorizationHandler->execute(
            $orderMock,
            self::PAYMENT_METHOD,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            []
        );
    }

    public function testExecuteSetsAmountAuthorizedOnFullAuthorization()
    {
        $paymentMock = $this->createMock(Payment::class);

        $orderMock = $this->createMockWithMethods(
            Order::class,
            [
                'getPayment',
                'getGrandTotal',
                'getBaseGrandTotal',
                'getEmailSent',
                'getIncrementId',
                'getStatus',
                'addCommentToStatusHistory',
                'setData'
            ],
            []
        );

        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getGrandTotal')->willReturn(self::GRAND_TOTAL);
        $orderMock->method('getBaseGrandTotal')->willReturn(self::BASE_GRAND_TOTAL);
        $orderMock->method('getEmailSent')->willReturn(false);
        $orderMock->method('getIncrementId')->willReturn('000000001');
        $orderMock->method('getStatus')->willReturn('processing');

        $this->paymentMethodsHelperMock->method('isAutoCapture')->willReturn(false);
        $this->adyenOrderPaymentHelperMock->method('isFullAmountAuthorized')->willReturn(true);
        $this->orderHelperMock->method('setPrePaymentAuthorized')->willReturn($orderMock);
        $this->caseManagementHelperMock->method('requiresManualReview')->willReturn(false);

        $paymentMock->expects($this->once())
            ->method('setAmountAuthorized')
            ->with(self::GRAND_TOTAL);
        $paymentMock->expects($this->once())
            ->method('setBaseAmountAuthorized')
            ->with(self::BASE_GRAND_TOTAL);

        $this->authorizationHandler->execute(
            $orderMock,
            self::PAYMENT_METHOD,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE,
            self::AMOUNT_CURRENCY,
            []
        );
    }
}
