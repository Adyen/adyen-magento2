<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Webhook\ManualReviewAcceptWebhookHandler;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order as MagentoOrder;
use PHPUnit\Framework\MockObject\MockObject;

class ManualReviewAcceptWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ManualReviewAcceptWebhookHandler $manualReviewAcceptWebhookHandler;
    private MagentoOrder|MockObject $order;
    private Notification|MockObject $notification;

    private const ORIGINAL_REFERENCE = 'original_reference';
    private const PSP_REFERENCE = 'ABCD1234GHJK5678';
    private const AMOUNT_VALUE = 500;
    private const PAYMENT_METHOD = 'ADYEN_CC';

    protected function setUp(): void
    {
        parent::setUp();

        $this->order = $this->createOrder();
        $this->notification = $this->createWebhook(
            self::ORIGINAL_REFERENCE,
            self::PSP_REFERENCE,
            self::AMOUNT_VALUE
        );
        $this->notification->method('getPaymentMethod')->willReturn(self::PAYMENT_METHOD);
    }

    private function createManualReviewAcceptWebhookHandler(
        $caseManagementHelper = null,
        $paymentMethodsHelper = null,
        $orderHelper = null
    ): ManualReviewAcceptWebhookHandler {
        if ($caseManagementHelper === null) {
            $caseManagementHelper = $this->createGeneratedMock(CaseManagement::class, ['markCaseAsAccepted']);
            $caseManagementHelper->method('markCaseAsAccepted')->willReturn($this->order);
        }
        if ($paymentMethodsHelper === null) {
            $paymentMethodsHelper = $this->createGeneratedMock(PaymentMethods::class);
        }
        if ($orderHelper === null) {
            $orderHelper = $this->createGeneratedMock(Order::class, ['finalizeOrder']);
        }

        return new ManualReviewAcceptWebhookHandler(
            $caseManagementHelper,
            $paymentMethodsHelper,
            $orderHelper
        );
    }

    public function testHandleWebhookMarksCaseAsAccepted()
    {
        $expectedComment = sprintf(
            'Manual review accepted for order w/pspReference: %s',
            self::ORIGINAL_REFERENCE
        );

        $caseManagementHelperMock = $this->createGeneratedMock(CaseManagement::class, ['markCaseAsAccepted']);
        $caseManagementHelperMock->expects($this->once())
            ->method('markCaseAsAccepted')
            ->with($this->order, $expectedComment)
            ->willReturn($this->order);

        $paymentMethodsHelperMock = $this->createGeneratedMock(PaymentMethods::class, ['isAutoCapture']);
        $paymentMethodsHelperMock->method('isAutoCapture')->willReturn(false);

        $this->manualReviewAcceptWebhookHandler = $this->createManualReviewAcceptWebhookHandler(
            $caseManagementHelperMock,
            $paymentMethodsHelperMock
        );

        $result = $this->manualReviewAcceptWebhookHandler->handleWebhook($this->order, $this->notification, 'active');

        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithAutoCaptureFinalizesOrder()
    {
        $caseManagementHelperMock = $this->createGeneratedMock(CaseManagement::class, ['markCaseAsAccepted']);
        $caseManagementHelperMock->method('markCaseAsAccepted')->willReturn($this->order);

        $paymentMethodsHelperMock = $this->createGeneratedMock(PaymentMethods::class, ['isAutoCapture']);
        $paymentMethodsHelperMock->method('isAutoCapture')
            ->with($this->order, self::PAYMENT_METHOD)
            ->willReturn(true);

        $orderHelperMock = $this->createGeneratedMock(Order::class, ['finalizeOrder']);
        $orderHelperMock->expects($this->once())
            ->method('finalizeOrder')
            ->with($this->order, self::PSP_REFERENCE, self::AMOUNT_VALUE)
            ->willReturn($this->order);

        $this->manualReviewAcceptWebhookHandler = $this->createManualReviewAcceptWebhookHandler(
            $caseManagementHelperMock,
            $paymentMethodsHelperMock,
            $orderHelperMock
        );

        $result = $this->manualReviewAcceptWebhookHandler->handleWebhook($this->order, $this->notification, 'active');

        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithManualCaptureDoesNotFinalizeOrder()
    {
        $caseManagementHelperMock = $this->createGeneratedMock(CaseManagement::class, ['markCaseAsAccepted']);
        $caseManagementHelperMock->method('markCaseAsAccepted')->willReturn($this->order);

        $paymentMethodsHelperMock = $this->createGeneratedMock(PaymentMethods::class, ['isAutoCapture']);
        $paymentMethodsHelperMock->method('isAutoCapture')
            ->with($this->order, self::PAYMENT_METHOD)
            ->willReturn(false);

        $orderHelperMock = $this->createGeneratedMock(Order::class, ['finalizeOrder']);
        $orderHelperMock->expects($this->never())->method('finalizeOrder');

        $this->manualReviewAcceptWebhookHandler = $this->createManualReviewAcceptWebhookHandler(
            $caseManagementHelperMock,
            $paymentMethodsHelperMock,
            $orderHelperMock
        );

        $result = $this->manualReviewAcceptWebhookHandler->handleWebhook($this->order, $this->notification, 'active');

        $this->assertSame($this->order, $result);
    }
}
