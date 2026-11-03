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
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Webhook\ManualReviewAcceptWebhookHandler;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\PaymentStates;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class ManualReviewAcceptWebhookHandlerTest extends AbstractAdyenTestCase
{
    private const ORIGINAL_REFERENCE = 'ORIGINAL_PSP_REFERENCE';
    private const PAYMENT_METHOD = 'visa';

    private Order|MockObject $orderMock;
    private Notification|MockObject $notificationMock;
    private CaseManagement|MockObject $caseManagementMock;
    private PaymentMethods|MockObject $paymentMethodsMock;
    private OrderHelper|MockObject $orderHelperMock;
    private AdyenOrderPayment|MockObject $adyenOrderPaymentMock;
    private ManualReviewAcceptWebhookHandler $webhookHandler;
    private ?string $acceptComment = null;

    protected function setUp(): void
    {
        $this->orderMock = $this->createMock(Order::class);
        $this->notificationMock = $this->createConfiguredMock(Notification::class, [
            'getOriginalReference' => self::ORIGINAL_REFERENCE,
            'getPaymentMethod' => self::PAYMENT_METHOD
        ]);

        $this->caseManagementMock = $this->createMock(CaseManagement::class);
        $this->caseManagementMock->expects($this->once())
            ->method('markCaseAsAccepted')
            ->with($this->orderMock, $this->stringContains(self::ORIGINAL_REFERENCE))
            ->willReturnCallback(function ($order, $comment) {
                $this->acceptComment = $comment;
                return $order;
            });

        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->adyenOrderPaymentMock = $this->createMock(AdyenOrderPayment::class);

        $this->webhookHandler = new ManualReviewAcceptWebhookHandler(
            $this->caseManagementMock,
            $this->paymentMethodsMock,
            $this->orderHelperMock,
            $this->adyenOrderPaymentMock
        );
    }

    public static function persistedCaptureModeProvider(): array
    {
        return [
            'persisted as auto capture' => [true, true],
            'persisted as manual capture (e.g. capture delay enforced)' => [false, false]
        ];
    }

    #[DataProvider('persistedCaptureModeProvider')]
    public function testHandleWebhookUsesPersistedCaptureMode(bool $isAutoCaptured, bool $shouldFinalize): void
    {
        $this->adyenOrderPaymentMock->expects($this->once())
            ->method('isAutoCaptured')
            ->with($this->orderMock, self::ORIGINAL_REFERENCE)
            ->willReturn($isAutoCaptured);

        $this->paymentMethodsMock->expects($this->never())->method('isAutoCapture');

        $this->orderHelperMock->expects($shouldFinalize ? $this->once() : $this->never())
            ->method('finalizeOrder')
            ->with($this->orderMock, $this->notificationMock)
            ->willReturn($this->orderMock);

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_PAID
        );

        $this->assertSame($this->orderMock, $result);
        $this->assertWaitingForCaptureComment(!$shouldFinalize);
    }

    public static function fallbackCaptureModeProvider(): array
    {
        return [
            'configured as auto capture' => [true],
            'configured as manual capture' => [false]
        ];
    }

    #[DataProvider('fallbackCaptureModeProvider')]
    public function testHandleWebhookFallsBackToConfiguredCaptureMode(bool $isAutoCapture): void
    {
        $this->adyenOrderPaymentMock->method('isAutoCaptured')->willReturn(null);

        $this->paymentMethodsMock->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->orderMock, self::PAYMENT_METHOD)
            ->willReturn($isAutoCapture);

        $this->orderHelperMock->expects($isAutoCapture ? $this->once() : $this->never())
            ->method('finalizeOrder')
            ->willReturn($this->orderMock);

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_PAID
        );

        $this->assertSame($this->orderMock, $result);
        $this->assertWaitingForCaptureComment(!$isAutoCapture);
    }

    private function assertWaitingForCaptureComment(bool $expected): void
    {
        $waitingForCaptureComment = 'The order will be finalized once the capture webhook is received.';

        $expected
            ? $this->assertStringContainsString($waitingForCaptureComment, $this->acceptComment)
            : $this->assertStringNotContainsString($waitingForCaptureComment, $this->acceptComment);
    }
}
