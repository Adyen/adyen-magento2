<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Webhook\CancelOrRefundWebhookHandler;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;
use phpseclib3\Crypt\DH\PublicKey;

class CancelOrRefundWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected $webhookHandler;
    protected $adyenLoggerMock;
    protected $serializerMock;
    protected $orderMock;

    public function testHandleWebhookWithCancel(){
        $notificationMock = $this->createWebhook();
        $orderId = 123;
        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock->method('getIncrementId')->willReturn($orderId);
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $this->orderMock->method('canCancel')->willReturn(true);

        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
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

        $transitionState = PaymentStates::STATE_CANCELLED;

        $resultOrder = $webhookHandler->handleWebhook($this->orderMock, $notificationMock, $transitionState);

        $this->assertInstanceOf(Order::class, $resultOrder);
    }

    public function testHandleWebhookWithRefund()
    {
        $notificationMock = $this->createWebhook();
        $orderId = 123;
        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderMock->method('getIncrementId')->willReturn($orderId);
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $this->orderMock->method('canCancel')->willReturn(false);
        $this->orderMock->method('canHold')->willReturn(false);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
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

        $transitionState = PaymentStates::STATE_REFUNDED;

        $resultOrder = $webhookHandler->handleWebhook($this->orderMock, $notificationMock, $transitionState);

        $this->assertInstanceOf(Order::class, $resultOrder);
    }

//    public function testHandleWebhookWithMissingModificationAction()
//    {
//        // Prepare the necessary data for the test
//        $notificationMock = $this->createWebhook();
//        $orderId = 123; // Replace with the actual order ID for the test
//
//        $this->orderMock = $this->createOrder();
//        $this->orderMock->method('getIncrementId')->willReturn($orderId);
//        $this->orderMock->method('isCanceled')->willReturn(false);
//        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);
//        $this->orderMock->method('canCancel')->willReturn(true);
//        $this->orderMock->method('holdCancelOrder')->willReturn($this->orderMock);
//
//        $webhookHandler = $this->createCancelOrRefundWebhookHandler();
//
//        $transitionState = PaymentStates::STATE_HOLDED;
//
//        // Call the handleWebhook method with the mock data (no modification.action in the additional data)
//        $resultOrder = $webhookHandler->handleWebhook($this->orderMock, $notificationMock, $transitionState);
//
//        // Add assertions to verify the expected behavior of the handler
//        $this->assertInstanceOf(Order::class, $resultOrder);
//
//    }

    protected function createCancelOrRefundWebhookHandler(
        $mockAdyenLogger = null,
        $mockSerializer = null,
        $mockOrderHelper = null
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

        return new CancelOrRefundWebhookHandler(
            $mockAdyenLogger,
            $mockSerializer,
            $mockOrderHelper
        );
    }

}
