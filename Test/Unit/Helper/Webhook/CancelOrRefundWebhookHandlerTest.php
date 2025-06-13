<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Webhook\CancelOrRefundWebhookHandler;
use Adyen\Payment\Model\CleanupAdditionalInformation;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;

class CancelOrRefundWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected $webhookHandler;
    protected $adyenLoggerMock;
    protected $serializerMock;
    protected $orderMock;

    // Set up before each test
    protected function setUp(): void
    {
        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
    }

    public function testHandleWebhookWithCancel(){
        $notificationMock =  $this->createMock(Notification::class);
        $orderId = 123;

        $paymentMock = $this->createMock(Order\Payment::class);

        $this->orderMock->method('getIncrementId')->willReturn($orderId);
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $this->orderMock->method('canCancel')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                sprintf('Attempting to cancel order %s', $orderId),
                [
                    'pspReference' => $notificationMock->getPspreference(),
                    'merchantReference' => $notificationMock->getMerchantReference()
                ]
            );

        $webhookHandler = $this->createCancelOrRefundWebhookHandler($this->adyenLoggerMock,null,null);

        $transitionState = PaymentStates::STATE_NEW;

        $resultOrder = $webhookHandler->handleWebhook($this->orderMock, $notificationMock, $transitionState);

        $this->assertInstanceOf(Order::class, $resultOrder);
    }

    public function testHandleWebhookWithRefund()
    {
        $notificationMock =  $this->createMock(Notification::class);
        $orderId = 123;

        $paymentMock = $this->createMock(Order\Payment::class);

        $this->orderMock->method('getIncrementId')->willReturn($orderId);
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $this->orderMock->method('canCancel')->willReturn(false);
        $this->orderMock->method('canHold')->willReturn(false);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                sprintf('Attempting to refund order %s', $orderId),
                [
                    'pspReference' => $notificationMock->getPspreference(),
                    'merchantReference' => $notificationMock->getMerchantReference()
                ]
            );
        $webhookHandler = $this->createCancelOrRefundWebhookHandler($this->adyenLoggerMock,null,null);

        $transitionState = PaymentStates::STATE_PAID;

        $resultOrder = $webhookHandler->handleWebhook($this->orderMock, $notificationMock, $transitionState);

        $this->assertInstanceOf(Order::class, $resultOrder);
    }

    public function testHandleWebhookWithMissingModificationAction()
    {
        // Prepare the necessary data for the test
        $notificationMock =  $this->createConfiguredMock(Notification::class, [
            'getAdditionalData' => ''
        ]);
        $orderId = 123; // Replace with the actual order ID for the test

        $this->orderMock = $this->createOrder();
        $this->orderMock->method('getIncrementId')->willReturn($orderId);
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $this->orderMock->method('canCancel')->willReturn(true);

        $webhookHandler = $this->createCancelOrRefundWebhookHandler();

        $transitionState = PaymentStates::STATE_NEW;

        // Call the handleWebhook method with the mock data (no modification.action in the additional data)
        $resultOrder = $webhookHandler->handleWebhook($this->orderMock, $notificationMock, $transitionState);

        // Add assertions to verify the expected behavior of the handler
        $this->assertInstanceOf(Order::class, $resultOrder);

    }

    public function testHandleWebhookWithOrderAlreadyCanceled()
    {
        $notificationMock =  $this->createMock(Notification::class);
        $orderId = 123;

        $paymentMock = $this->createMock(Order\Payment::class);

        $this->orderMock->method('getIncrementId')->willReturn($orderId);
        $this->orderMock->method('isCanceled')->willReturn(true);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $this->orderMock->method('canCancel')->willReturn(false);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                sprintf('Order %s is already cancelled or held, so do nothing', $orderId),
                [
                    'pspReference' => $notificationMock->getPspreference(),
                    'merchantReference' => $notificationMock->getMerchantReference()
                ]
            );

        $webhookHandler = $this->createCancelOrRefundWebhookHandler($this->adyenLoggerMock);

        $transitionState = PaymentStates::STATE_CANCELLED;

        $resultOrder = $webhookHandler->handleWebhook($this->orderMock, $notificationMock, $transitionState);

        $this->assertInstanceOf(Order::class, $resultOrder);
    }

    protected function createCancelOrRefundWebhookHandler(
        $mockAdyenLogger = null,
        $mockSerializer = null,
        $mockOrderHelper = null,
        $cleanupAdditionalInformation = null

    ): CancelOrRefundWebhookHandler
    {
        if (is_null($mockAdyenLogger)) {
            $mockAdyenLogger = $this->createMock(AdyenLogger::class);
        }
        if (is_null($mockSerializer)) {
            $mockSerializer = $this->createMock(SerializerInterface::class);
        }
        if (is_null($mockOrderHelper)) {
            $mockOrderHelper = $this->createMock(OrderHelper::class);
        }
        if (is_null($cleanupAdditionalInformation)) {
            $cleanupAdditionalInformation = $this->createMock(CleanupAdditionalInformation::class);
        }

        return new CancelOrRefundWebhookHandler(
            $mockAdyenLogger,
            $mockSerializer,
            $mockOrderHelper,
            $cleanupAdditionalInformation
        );
    }

}
