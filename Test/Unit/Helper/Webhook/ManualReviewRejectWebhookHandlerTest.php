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
namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Webhook\ManualReviewRejectWebhookHandler;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\PaymentStates;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class ManualReviewRejectWebhookHandlerTest extends AbstractAdyenTestCase
{
    private const ORIGINAL_REFERENCE = 'ORIGINAL_PSP_REFERENCE';
    private const PAYMENT_METHOD = 'visa';

    private Order|MockObject $orderMock;
    private Notification|MockObject $notificationMock;
    private CaseManagement|MockObject $caseManagementMock;
    private PaymentMethods|MockObject $paymentMethodsMock;
    private AdyenOrderPayment|MockObject $adyenOrderPaymentMock;
    private ManualReviewRejectWebhookHandler $webhookHandler;

    protected function setUp(): void
    {
        $this->orderMock = $this->createMock(Order::class);
        $this->notificationMock = $this->createConfiguredMock(Notification::class, [
            'getOriginalReference' => self::ORIGINAL_REFERENCE,
            'getPaymentMethod' => self::PAYMENT_METHOD
        ]);

        $this->caseManagementMock = $this->createMock(CaseManagement::class);
        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $this->adyenOrderPaymentMock = $this->createMock(AdyenOrderPayment::class);

        $this->webhookHandler = new ManualReviewRejectWebhookHandler(
            $this->caseManagementMock,
            $this->paymentMethodsMock,
            $this->adyenOrderPaymentMock
        );
    }

    public static function captureModeProvider(): array
    {
        return [
            'persisted as auto capture' => [true],
            'persisted as manual capture (e.g. capture delay enforced)' => [false]
        ];
    }

    #[DataProvider('captureModeProvider')]
    public function testHandleWebhookUsesPersistedCaptureMode(bool $isAutoCaptured): void
    {
        $this->adyenOrderPaymentMock->expects($this->once())
            ->method('isAutoCaptured')
            ->with($this->orderMock, self::ORIGINAL_REFERENCE)
            ->willReturn($isAutoCaptured);

        $this->paymentMethodsMock->expects($this->never())->method('isAutoCapture');

        $this->caseManagementMock->expects($this->once())
            ->method('markCaseAsRejected')
            ->with($this->orderMock, self::ORIGINAL_REFERENCE, $isAutoCaptured)
            ->willReturn($this->orderMock);

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_CANCELLED
        );

        $this->assertSame($this->orderMock, $result);
    }

    #[DataProvider('captureModeProvider')]
    public function testHandleWebhookFallsBackToConfiguredCaptureMode(bool $isAutoCapture): void
    {
        $this->adyenOrderPaymentMock->method('isAutoCaptured')->willReturn(null);

        $this->paymentMethodsMock->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->orderMock, self::PAYMENT_METHOD)
            ->willReturn($isAutoCapture);

        $this->caseManagementMock->expects($this->once())
            ->method('markCaseAsRejected')
            ->with($this->orderMock, self::ORIGINAL_REFERENCE, $isAutoCapture)
            ->willReturn($this->orderMock);

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_CANCELLED
        );

        $this->assertSame($this->orderMock, $result);
    }
}
