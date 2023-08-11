<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Webhook\AuthorisationWebhookHandler;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class AuthorisationWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected AuthorisationWebhookHandler $handler;

    protected function setUp(): void
    {
        // Create mock objects for dependencies
        $adyenOrderPaymentHelperMock = $this->createMock(AdyenOrderPayment::class);
        $orderHelperMock = $this->createMock(OrderHelper::class);
        $caseManagementHelperMock = $this->createMock(CaseManagement::class);
        $serializerMock = $this->createMock(SerializerInterface::class);
        $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $configHelperMock = $this->createMock(Config::class);
        $invoiceHelperMock = $this->createMock(Invoice::class);
        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);

        // Create an instance of the AuthorisationWebhookHandler class with mocked dependencies
        $this->handler = new AuthorisationWebhookHandler(
            $adyenOrderPaymentHelperMock,
            $orderHelperMock,
            $caseManagementHelperMock,
            $serializerMock,
            $adyenLoggerMock,
            $chargedCurrencyMock,
            $configHelperMock,
            $invoiceHelperMock,
            $paymentMethodsHelperMock
        );




//        // Mocks
//        $this->orderPaymentHelper = $this->createMock(AdyenOrderPayment::class);
//        $this->orderHelper = $this->createMock(OrderHelper::class);
//        $this->caseManagementHelper = $this->createMock(CaseManagement::class);
//        $this->orderPayment = $this->createConfiguredMock(OrderPaymentInterface::class, [
//            'getAdditionalInformation' => ['payByLinkFailureCount' => 2],
//        ]);
//
//        $this->order = $this->getMockBuilder(Order::class)
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $this->logger = $this->createMock(AdyenLogger::class);
//
//        $this->notification = $this->getMockBuilder(Notification::class)
//            ->disableOriginalConstructor()
//            ->getMock();
    }

    public function testHandleWebhookWithSuccessfulState()
    {
        // Expectations
        $this->notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderHelper->expects($this->once())
            ->method('setPrePaymentAuthorized')
            ->with($this->equalTo($this->order))
            ->willReturn($this->order);

        $this->orderHelper->expects($this->once())
            ->method('updatePaymentDetails')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification));

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            null,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        $this->assertSame($this->order, $result);
    }








    public function testHandleWebhookWithFailedStateWithoutPreviousPaymentCaptured()
    {
        $notificationMock =  $this->createConfiguredMock(Notification::class, [
            'getAdditionalData' => ''
        ]);

        $this->order->expects($this->once())
            ->method('getData')
            ->with('adyen_notification_event_code')
            ->willReturn('AUTHORISATION : FALSE');

        // Expectation for logging and order status
        $this->logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Order is not cancelled because previous notification was an authorisation that succeeded and payment was captured'));

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

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

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

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

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithPartialPaymentFailure()
    {
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('ADYEN_CC'); //Can be anything, just testing a scenario

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

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

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

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithAutoCaptureDisabled()
    {
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('ADYEN_CC');

        $this->orderPaymentHelper->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->equalTo($this->order), $this->equalTo('ADYEN_CC'))
            ->willReturn(false); // Example: auto-capture disabled

        $this->orderHelper->expects($this->never())
            ->method('setPrePaymentAuthorized');

        $this->orderHelper->expects($this->once())
            ->method('updatePaymentDetails')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification));

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        // Assert the order was not changed
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithPartialPaymentAuthorized()
    {
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('ADYEN_CC');

        $this->order->expects($this->never())
            ->method('getData')
            ->with('adyen_notification_payment_captured');

        $this->orderPaymentHelper->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->equalTo($this->order), $this->equalTo('ADYEN_CC'))
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

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            null,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

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

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            $this->caseManagementHelper,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method for AutoCapture and manual review
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithManualCaptureAndNoReview()
    {
        // Expectations for ManualCapture and no review
        $this->notification->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn('ADYEN_CC');

        $this->caseManagementHelper->expects($this->once())
            ->method('requiresManualReview')
            ->willReturn(false);

        $this->orderHelper->expects($this->once())
            ->method('finalizeOrder')
            ->willReturn($this->order);

        $this->orderPaymentHelper->expects($this->once())
            ->method('isAutoCapture')
            ->with($this->equalTo($this->order), $this->equalTo('some_payment_method'))
            ->willReturn(false); // Example: auto-capture disabled

        $this->orderHelper->expects($this->once())
            ->method('isFullAmountAuthorized')
            ->with($this->equalTo($this->order))
            ->willReturn(true); // Example: full amount authorized

        $this->order->expects($this->once())
            ->method('setState')
            ->with($this->equalTo(Order::STATE_PENDING_PAYMENT));

        $this->order->expects($this->once())
            ->method('setStatus')
            ->with($this->equalTo($this->order->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT)));

        $this->orderHelper->expects($this->once())
            ->method('addWebhookStatusHistoryComment')
            ->with($this->equalTo($this->order), $this->equalTo($this->notification))
            ->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($this->equalTo(__('Capture Mode set to Manual')), $this->equalTo($this->order->getStatus()));

        $this->logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->equalTo('Capture mode is set to Manual'), $this->anything());

        // Create the AuthorisationWebhookHandler instance with mocked dependencies
        $handler = $this->createAuthorisationWebhookHandler(
            $this->orderPaymentHelper,
            $this->orderHelper,
            $this->caseManagementHelper,
            null,
            $this->logger,
            null,
            null,
            null,
            null
        );

        // Test the handleWebhook method for ManualCapture and no review
        $result = $handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_PAID);

        $this->assertSame($this->order, $result);
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
        $this->order->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->orderPayment);

        $this->orderPayment->expects($this->once())
            ->method('getAdditionalInformation')
            ->with($this->equalTo('payByLinkFailureCount'))
            ->willReturn(2); // Example: failure count less than max

        $this->orderPayment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with($this->equalTo('payByLinkFailureCount'), $this->equalTo(3)); // Incremented failure count

        $this->notification->expects($this->once())
            ->method('setDone')
            ->with($this->equalTo(true));

        $this->notification->expects($this->once())
            ->method('setProcessing')
            ->with($this->equalTo(false));

        $this->notification->expects($this->once())
            ->method('save');

        $this->order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($this->equalTo(__(sprintf(
                "Order wasn't cancelled by this webhook notification. Pay by Link failure count: %s/%s",
                3,
                AdyenPayByLinkConfigProvider::MAX_FAILURE_COUNT
            ))), $this->equalTo(false));

        $this->logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->equalTo(__(sprintf(
                "Order wasn't cancelled by this webhook notification. Pay by Link failure count: %s/%s",
                3,
                AdyenPayByLinkConfigProvider::MAX_FAILURE_COUNT
            ))), $this->anything());

        // Test the handleWebhook method
        $result = $this->handler->handleWebhook($this->order, $this->notification, PaymentStates::STATE_FAILED);

        $this->assertSame($this->order, $result);
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
