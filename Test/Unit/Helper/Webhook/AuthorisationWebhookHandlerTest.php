<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Webhook\AuthorisationWebhookHandler;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class AuthorisationWebhookHandlerTest extends TestCase
{
    public function testHandleWebhookWithSuccessfulState()
    {
        // Mocks
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderPaymentHelper = $this->createMock(OrderPaymentHelper::class);
        $orderHelper = $this->createMock(OrderHelper::class);
        // ... create mocks for other dependencies ...

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = new AuthorisationWebhookHandler(
            $orderPaymentHelper,
            $orderHelper,
        // ... pass other mocked dependencies ...
        );

        // Expectations
        $orderPaymentHelper->expects($this->once())
            ->method('createAdyenOrderPayment')
            ->with($this->equalTo($order), $this->equalTo($notification))
            ->willReturn($order);

        $orderHelper->expects($this->once())
            ->method('setPrePaymentAuthorized')
            ->with($this->equalTo($order))
            ->willReturn($order);

        $orderHelper->expects($this->once())
            ->method('updatePaymentDetails')
            ->with($this->equalTo($order), $this->equalTo($notification));

        // ... set up other expectations ...

        // Test the handleWebhook method
        $result = $handler->handleWebhook($order, $notification, PaymentStates::STATE_PAID);

        $this->assertSame($order, $result);
    }

    public function testHandleWebhookWithFailedState()
    {
        // Mocks
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderPaymentHelper = $this->createMock(OrderPaymentHelper::class);
        $orderHelper = $this->createMock(OrderHelper::class);
        // ... create mocks for other dependencies ...

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = new AuthorisationWebhookHandler(
            $orderPaymentHelper,
            $orderHelper,
        // ... pass other mocked dependencies ...
        );

        // Expectations for failed state
        $orderHelper->expects($this->once())
            ->method('addWebhookStatusHistoryComment')
            ->with($this->equalTo($order), $this->equalTo($notification));

        // ...

        // Test the handleWebhook method for failed state
        $result = $handler->handleWebhook($order, $notification, PaymentStates::STATE_FAILED);

        $this->assertSame($order, $result);
    }


    public function testHandleWebhookWithAutoCaptureAndManualReview()
    {
        // Mocks
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Expectations for AutoCapture and manual review
        $notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('adyen_credit_card'); // Assuming this triggers auto-capture

        $caseManagementHelper->expects($this->once())
            ->method('requiresManualReview')
            ->willReturn(true);

        $caseManagementHelper->expects($this->once())
            ->method('markCaseAsPendingReview')
            ->willReturn($order);

        // ... set up other expectations ...

        // Test the handleWebhook method for AutoCapture and manual review
        $result = $handler->handleWebhook($order, $notification, PaymentStates::STATE_PAID);

        $this->assertSame($order, $result);
    }

    public function testHandleWebhookWithManualCaptureAndNoReview()
    {
        // ... create mocks and setup ...

        // Expectations for ManualCapture and no review
        $notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('some_payment_method');

        $caseManagementHelper->expects($this->once())
            ->method('requiresManualReview')
            ->willReturn(false);

        $orderHelper->expects($this->once())
            ->method('finalizeOrder')
            ->willReturn($order);

        // ... set up other expectations ...

        // Test the handleWebhook method for ManualCapture and no review
        $result = $handler->handleWebhook($order, $notification, PaymentStates::STATE_PAID);

        $this->assertSame($order, $result);
    }

    public function testCanCancelPayByLinkOrderWithMaxFailureCount()
    {
        // Mocks
        $order = $this->createConfiguredMock(MagentoOrder::class, [
            'getPayment' => $this->createMock(OrderPaymentInterface::class),
        ]);

        $notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderPayment = $this->createConfiguredMock(OrderPaymentInterface::class, [
            'getAdditionalInformation' => ['payByLinkFailureCount' => 2],
        ]);

        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($orderPayment);

        $notification->expects($this->once())
            ->method('setDone')
            ->with(true);

        $notification->expects($this->once())
            ->method('setProcessing')
            ->with(false);

        $notification->expects($this->once())
            ->method('save');

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($this->equalTo($order), $this->stringContains('Pay by Link failure count: 2/3'));

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Pay by Link failure count: 2/3'));

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = new AuthorisationWebhookHandler(
        // ... pass other mocked dependencies ...
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($order, $notification, PaymentStates::STATE_FAILED);

        $this->assertSame($order, $result);
    }


    public function testHandleWebhookWithPayByLinkLessThanMaxFailureCount()
    {
        // Mocks
        $order = $this->createConfiguredMock(MagentoOrder::class, [
            'getPayment' => $this->createMock(OrderPaymentInterface::class),
        ]);

        $notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderPayment = $this->createConfiguredMock(OrderPaymentInterface::class, [
            'getAdditionalInformation' => ['payByLinkFailureCount' => 1],
        ]);

        $order->expects($this->once())
            ->method('getPayment')
            ->willReturn($orderPayment);

        $notification->expects($this->once())
            ->method('setDone')
            ->with(true);

        $notification->expects($this->once())
            ->method('setProcessing')
            ->with(false);

        $notification->expects($this->once())
            ->method('save');

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($this->equalTo($order), $this->stringContains('Pay by Link failure count: 1/3'));

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Pay by Link failure count: 1/3'));

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $orderPayment,
            $orderHelper,
            null,
            null,
            $logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($order, $notification, PaymentStates::STATE_FAILED);

        $this->assertSame($order, $result);
    }

    protected function createAuthorisationWebhookHandler(
        $mockAdyenOrderPayment = null,
        $mockOrderHelper = null,
        $mockCaseManagementHelper = null,
        $mockSerializer = null,
        $mockAdyenLogger = null,
        $mockChargedCurrency = null,
        $mockConfigHelper = null,
        $mockInvoiceHelper = null,
        $mockPaymentMethodsHelper = null
    ): AuthorisationWebhookHandler
    {
        if (is_null($mockAdyenOrderPayment)) {
            $mockAdyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        }

        if (is_null($mockOrderHelper)) {
            $mockOrderHelper = $this->createMock(OrderHelper::class);
        }

        if (is_null($mockCaseManagementHelper)) {
            $mockCaseManagementHelper = $this->createMock(CaseManagement::class);
        }

        if (is_null($mockSerializer)) {
            $mockSerializer = $this->createMock(SerializerInterface::class);
        }

        if (is_null($mockAdyenLogger)) {
            $mockAdyenLogger = $this->createMock(AdyenLogger::class);
        }

        if (is_null($mockChargedCurrency)) {
            $mockChargedCurrency = $this->createMock(ChargedCurrency::class);
        }

        if (is_null($mockConfigHelper)) {
            $mockConfigHelper = $this->createMock(Config::class);
        }

        if (is_null($mockInvoiceHelper)) {
            $mockInvoiceHelper = $this->createMock(Invoice::class);
        }

        if (is_null($mockPaymentMethodsHelper)) {
            $mockPaymentMethodsHelper = $this->createMock(PaymentMethods::class);
        }

        return new AuthorisationWebhookHandler(
            $mockAdyenOrderPayment,
            $mockOrderHelper,
            $mockCaseManagementHelper,
            $mockSerializer,
            $mockAdyenLogger,
            $mockChargedCurrency,
            $mockConfigHelper,
            $mockInvoiceHelper,
            $mockPaymentMethodsHelper
        );
    }

}
