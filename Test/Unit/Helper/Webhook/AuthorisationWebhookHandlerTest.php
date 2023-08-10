<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Webhook\AuthorisationWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;


class AuthorisationWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ObjectManager $objectManager;
    protected AuthorisationWebhookHandler $handler;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        // Mocks
        $this->orderPaymentHelper = $this->createMock(AdyenOrderPayment::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->caseManagementHelper = $this->createMock(CaseManagement::class);
        $this->orderPayment = $this->createConfiguredMock(OrderPaymentInterface::class, [
            'getAdditionalInformation' => ['payByLinkFailureCount' => 2],
        ]);

        $this->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->logger = $this->createMock(AdyenLogger::class);

        $this->notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();
        // ... create other mocks ...

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $this->handler = new AuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
        // ... pass other mocked dependencies ...
        );
    }

    public function testHandleWebhookWithSuccessfulState()
    {

        // Expectations
        $this->orderPaymentHelper->expects($this->once())
            ->method('createAdyenOrderPayment')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification))
            ->willReturn($this->order);

        $this->orderHelper->expects($this->once())
            ->method('setPrePaymentAuthorized')
            ->with($this->equalTo($this->order))
            ->willReturn($this->order);

        $this->orderHelper->expects($this->once())
            ->method('updatePaymentDetails')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification));

        // ... set up other expectations ...

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithFailedState()
    {
        // Expectations for failed state
        $this->orderHelper->expects($this->once())
            ->method('addWebhookStatusHistoryComment')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification));

        // Test the handleWebhook method for failed state
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithFailedStateWithoutPreviousPaymentCaptured()
    {
        $this->notification->expects($this->once())
            ->method('getAdditionalData')
            ->willReturn([]);

        $this->order->expects($this->once())
            ->method('getData')
            ->with('adyen_notification_event_code')
            ->willReturn('AUTHORISATION : FALSE');

        // Expectation for logging and order status
        $this->logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Order is not cancelled because previous notification was an authorisation that succeeded and payment was captured'));

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithFailedStateWithPreviousPaymentCaptured()
    {
        $this->notification->expects($this->once())
            ->method('getAdditionalData')
            ->willReturn([]);

        $this->order->expects($this->once())
            ->method('getData')
            ->with('adyen_notification_event_code')
            ->willReturn('AUTHORISATION : TRUE');

        $this->order->expects($this->once())
            ->method('getData')
            ->with('adyen_notification_payment_captured')
            ->willReturn(true); // Example payment captured

        // Expectation for logging and order status
        $this->logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Order is not cancelled because previous notification was an authorisation that succeeded and payment was captured'));

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithCanceledOrHoldedOrder()
    {
        $this->order->expects($this->once())
            ->method('isCanceled')
            ->willReturn(true); // Example: order is canceled

        $this->order->expects($this->never())
            ->method('cancel');

        // Expectation for logging
        $this->logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Order is already cancelled or held, do nothing'));

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithPartialPaymentFailure()
    {
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('c_cash'); //Can be anything, just testing a scenario

        $this->order->expects($this->once())
            ->method('getData')
            ->with('adyen_notification_payment_captured')
            ->willReturn(false); // Example: payment not captured

        $this->orderPaymentHelper->expects($this->once())
            ->method('createAdyenOrderPayment')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification))
            ->willReturn($this->order);

        $this->orderHelper->expects($this->once())
            ->method('isFullAmountAuthorized')
            ->with($this->equalTo($this->order))
            ->willReturn(false); // Example: partial payment

        $this->orderHelper->expects($this->never())
            ->method('setPrePaymentAuthorized');

        $this->orderHelper->expects($this->never())
            ->method('updatePaymentDetails');

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithOrderAlreadyCaptured()
    {
        // Mocks and expectations
        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn(Order::STATE_COMPLETE);

        $this->orderHelper->expects($this->never())
            ->method('setPrePaymentAuthorized');

        $this->orderHelper->expects($this->never())
            ->method('updatePaymentDetails');

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithAutoCaptureDisabled()
    {
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('c_cash');

        $this->orderPaymentHelper->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->equalTo($this->order), $this->equalTo('c_cash'))
            ->willReturn(false); // Example: auto-capture disabled

        $this->orderHelper->expects($this->never())
            ->method('setPrePaymentAuthorized');

        $this->orderHelper->expects($this->once())
            ->method('updatePaymentDetails')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification));

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithPartialPaymentAuthorized()
    {
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('c_cash');

        $this->order->expects($this->never())
            ->method('getData')
            ->with('adyen_notification_payment_captured');

        $this->orderPaymentHelper->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->equalTo($this->order), $this->equalTo('c_cash'))
            ->willReturn(true); // Example: auto-capture enabled

        $this->orderHelper->expects($this->once())
            ->method('isFullAmountAuthorized')
            ->with($this->equalTo($this->order))
            ->willReturn(false); // Example: partial payment

        $this->orderHelper->expects($this->once())
            ->method('setPrePaymentAuthorized')
            ->with($this->equalTo($this->order))
            ->willReturn($this->order);

        $this->orderHelper->expects($this->once())
            ->method('updatePaymentDetails')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification));

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }


    public function testHandleWebhookWithAutoCaptureAndManualReview()
    {
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('adyen_credit_card'); // Assuming this triggers auto-capture

        $this->caseManagementHelper->expects($this->once())
            ->method('requiresManualReview')
            ->willReturn(true);


        $this->orderPaymentHelper->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->equalTo($this->order), $this->equalTo('some_payment_method'))
            ->willReturn(true); // Example: auto-capture enabled

        $this->orderHelper->expects($this->once())
            ->method('isFullAmountAuthorized')
            ->with($this->equalTo($this->order))
            ->willReturn(true); // Example: full amount authorized

        $this->caseManagementHelper->expects($this->once())
            ->method('requiresManualReview')
            ->willReturn(true); // Example: manual review required

        $this->caseManagementHelper->expects($this->once())
            ->method('markCaseAsPendingReview')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification->getPspreference()), $this->equalTo(true))
            ->willReturn($this->order);

        // Test the handleWebhook method for AutoCapture and manual review
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithManualCaptureAndNoReview()
    {
        // Expectations for ManualCapture and no review
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('c_cash');

        $this->caseManagementHelper->expects($this->once())
            ->method('requiresManualReview')
            ->willReturn(false);

        $this->orderHelper->expects($this->once())
            ->method('finalizeOrder')
            ->willReturn($this->order);

        $orderPaymentHelper->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->equalTo($order), $this->equalTo('some_payment_method'))
            ->willReturn(false); // Example: auto-capture disabled

        $orderHelper->expects($this->once())
            ->method('isFullAmountAuthorized')
            ->with($this->equalTo($order))
            ->willReturn(true); // Example: full amount authorized

        $order->expects($this->once())
            ->method('setState')
            ->with($this->equalTo(Order::STATE_PENDING_PAYMENT));

        $order->expects($this->once())
            ->method('setStatus')
            ->with($this->equalTo($order->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT)));

        $orderHelper->expects($this->once())
            ->method('addWebhookStatusHistoryComment')
            ->with($this->equalTo($order), $this->equalTo($notification))
            ->willReturn($order);

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($this->equalTo(__('Capture Mode set to Manual')), $this->equalTo($order->getStatus()));

        $adyenLogger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->equalTo('Capture mode is set to Manual'), $this->anything());

        // Test the handleWebhook method for ManualCapture and no review
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        $this->assertSame($this->order, $result);
        // ... assertions ...
    }







    public function testCanCancelPayByLinkOrderWithMaxFailureCount()
    {
        $this->order->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->orderPayment);

        $this->notification->expects($this->once())
            ->method('setDone')
            ->with(true);

        $this->notification->expects($this->once())
            ->method('setProcessing')
            ->with(false);

        $this->notification->expects($this->once())
            ->method('save');

        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->orderHelper->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($this->equalTo($this->order), $this->stringContains('Pay by Link failure count: 2/5'));

        $this->logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Pay by Link failure count: 2/3'));

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        $this->assertSame($this->order, $result);
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
