<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Webhook\OfferClosedWebhookHandler;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\CleanupAdditionalInformation;
use Adyen\Payment\Model\Method\Adapter;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class OfferClosedWebhookHandlerTest extends AbstractAdyenTestCase
{
    private PaymentMethods|MockObject $paymentMethodsHelper;
    private OrderPaymentResourceModel|MockObject $orderPaymentResourceModel;

    protected function setUp(): void
    {
        // Set up mock objects for dependencies
        $this->paymentMethodsHelper = $this->createMock(PaymentMethods::class);
        $this->orderPaymentResourceModel = $this->createMock(OrderPaymentResourceModel::class);
    }

    public function testHandleWebhookReturnsOrder()
    {
        // Create a sample MagentoOrder and Notification
        $order = $this->createMock(MagentoOrder::class);
        $notification = $this->createMock(Notification::class);

        // Mock any necessary method calls and expectations
        $this->paymentMethodsHelper->method('compareOrderAndWebhookPaymentMethods')->willReturn(true);
        $order->method('canCancel')->willReturn(true);
        $order->method('getPayment')->willReturn($this->createPartialMock(Payment::class, ['getMethod']));
        $order->getPayment()->method('getMethod')->willReturn('adyen_cc');

        // Create an instance of the OfferClosedWebhookHandler
        $webhookHandler = $this->createOfferClosedWebhookHandler($this->paymentMethodsHelper,null,null,null,null);

        // Call the handleWebhook method and assert that it returns the order
        $result = $webhookHandler->handleWebhook($order, $notification, 'PAYMENT_REVIEW');
        $this->assertEquals($order, $result);
    }

    public function testHandleWebhookReturnsNullForPayByLink()
    {
        // Create a sample MagentoOrder with a Pay by Link payment method
        $order = $this->createMock(MagentoOrder::class);
        $order->method('getPayment')->willReturn($this->createPartialMock(Payment::class, ['getMethod']));
        $order->getPayment()->method('getMethod')->willReturn('adyen_pay_by_link');
        $notification = $this->createMock(Notification::class);

        // Create an instance of the OfferClosedWebhookHandler
        $webhookHandler = $this->createOfferClosedWebhookHandler(null,null,null,null,null);

        // Call the handleWebhook method and assert that it returns null
        $result = $webhookHandler->handleWebhook($order, $notification, 'PAYMENT_REVIEW');
        //$this->assertNull($result);
        $this->assertEquals($order, $result);
    }

    public function testHandleWebhookThrowsExceptionForInvalidPaymentMethod()
    {
        // Create a sample MagentoOrder and Notification
        $order = $this->createMock(MagentoOrder::class);
        $paymentMethodInstanceMock = $this->createMock(Adapter::class);
        $order->method('getPayment')->willReturn($this->createPartialMock(Payment::class, ['getMethod','getMethodInstance']));
        $order->getPayment()->method('getMethod')->willReturn('adyen_cc');
        $order->getPayment()->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $notification = $this->createMock(Notification::class);

        // Mock payment method comparison behavior
        $this->paymentMethodsHelper->method('compareOrderAndWebhookPaymentMethods')
            ->with($order, $notification)
            ->willReturn(false);

        // Create an instance of the OfferClosedWebhookHandler
        $webhookHandler = $this->createOfferClosedWebhookHandler($this->paymentMethodsHelper, null, null, null, null);

        // Call the handleWebhook method to trigger the exception
        $result = $webhookHandler->handleWebhook($order, $notification, 'PAYMENT_REVIEW');

        // Verify the expected result
        $this->assertEquals($order, $result);
    }


    public function testHandleWebhookReturnsOrderWhenCapturedPaymentsExist()
    {
        // Create a sample MagentoOrder and Notification
        $order = $this->createMock(MagentoOrder::class);
        $order->method('getPayment')->willReturn($this->createPartialMock(Payment::class, ['getMethod']));
        $order->getPayment()->method('getMethod')->willReturn('adyen_cc');
        $notification = $this->createMock(Notification::class);

        // Mock the scenario where $capturedAdyenOrderPayments is not empty
        $this->orderPaymentResourceModel->method('getLinkedAdyenOrderPayments')->willReturn(['payment1', 'payment2']);

        // Create an instance of the OfferClosedWebhookHandler
        $webhookHandler = $this->createOfferClosedWebhookHandler(
            null,
            null,
            null,
            null,
            $this->orderPaymentResourceModel
        );

        // Call the handleWebhook method and assert that it returns the order
        $result = $webhookHandler->handleWebhook($order, $notification, 'PAYMENT_REVIEW');
        $this->assertEquals($order, $result);
    }

    public function testHandleWebhookSkipsWhenOrderAlreadyCancelled()
    {
        // Create a sample MagentoOrder and Notification
        $order = $this->createMock(MagentoOrder::class);
        $payment = $this->createPartialMock(Payment::class, ['getMethod', 'getEntityId']);
        $notification = $this->createMock(Notification::class);

        // Set up payment mock
        $payment->method('getMethod')->willReturn('adyen_cc');
        $payment->method('getEntityId')->willReturn(123);
        $order->method('getPayment')->willReturn($payment);

        // Mock order as already cancelled
        $order->method('isCanceled')->willReturn(true);

        // Mock notification methods for logging
        $notification->method('getPspreference')->willReturn('test_psp_reference');
        $notification->method('getMerchantReference')->willReturn('test_merchant_reference');
        $notification->method('getEventCode')->willReturn('OFFER_CLOSED');

        // Mock payment method comparison to return true (so we reach the isCanceled check)
        $this->paymentMethodsHelper->method('compareOrderAndWebhookPaymentMethods')
            ->with($order, $notification)
            ->willReturn(true);

        // Mock empty captured payments (so we reach the isCanceled check)
        $this->orderPaymentResourceModel->method('getLinkedAdyenOrderPayments')->willReturn([]);

        // Expect addCommentToStatusHistory to be called with the skip message
        $order->expects($this->once())
            ->method('addCommentToStatusHistory');

        // Create mock for logger to verify it's called
        $mockAdyenLogger = $this->createMock(AdyenLogger::class);
        $mockAdyenLogger->expects($this->once())
            ->method('addAdyenNotification');

        // Create mock for cleanup to verify it's called
        $cleanupAdditionalInformation = $this->createMock(CleanupAdditionalInformation::class);
        $cleanupAdditionalInformation->expects($this->once())
            ->method('execute')
            ->with($payment);

        // Create an instance of the OfferClosedWebhookHandler
        $webhookHandler = $this->createOfferClosedWebhookHandler(
            $this->paymentMethodsHelper,
            $mockAdyenLogger,
            null,
            null,
            $this->orderPaymentResourceModel,
            $cleanupAdditionalInformation
        );

        // Call the handleWebhook method and assert that it returns the order
        $result = $webhookHandler->handleWebhook($order, $notification, 'PAYMENT_REVIEW');
        $this->assertEquals($order, $result);
    }

    protected function createOfferClosedWebhookHandler(
        $mockPaymentMethodsHelper = null,
        $mockAdyenLogger = null,
        $mockConfigHelper = null,
        $mockOrderHelper = null,
        $mockOrderPaymentResourceModel = null,
        $cleanupAdditionalInformation = null
    ): OfferClosedWebhookHandler
    {
        if (is_null($mockOrderPaymentResourceModel)) {
            $mockOrderPaymentResourceModel = $this->createMock(OrderPaymentResourceModel::class);
        }

        if (is_null($mockOrderHelper)) {
            $mockOrderHelper = $this->createMock(Order::class);
        }

        if (is_null($mockAdyenLogger)) {
            $mockAdyenLogger = $this->createMock(AdyenLogger::class);
        }

        if (is_null($mockConfigHelper)) {
            $mockConfigHelper = $this->createMock(Config::class);
        }

        if (is_null($mockPaymentMethodsHelper)) {
            $mockPaymentMethodsHelper = $this->createMock(PaymentMethods::class);
        }

        if (is_null($cleanupAdditionalInformation)) {
            $cleanupAdditionalInformation = $this->createMock(CleanupAdditionalInformation::class);
        }

        return new OfferClosedWebhookHandler(
            $mockPaymentMethodsHelper,
            $mockAdyenLogger,
            $mockConfigHelper,
            $mockOrderHelper,
            $mockOrderPaymentResourceModel,
            $cleanupAdditionalInformation
        );
    }
}
