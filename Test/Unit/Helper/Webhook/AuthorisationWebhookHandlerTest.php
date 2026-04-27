<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenCcConfigProviderTest;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\Webhook\AuthorisationWebhookHandler;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Serialize\SerializerInterface;
use ReflectionClass;
use ReflectionException as ReflectionExceptionAlias;
use ReflectionMethod;

class AuthorisationWebhookHandlerTest extends AbstractAdyenTestCase
{
    private Notification|MockObject $notificationMock;
    private Order|MockObject $orderMock;
    private Quote|MockObject $quoteMock;
    private AdyenOrderPayment|MockObject $adyenOrderPaymentMock;
    private OrderHelper|MockObject $orderHelperMock;
    private CaseManagement|MockObject $caseManagementMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderMock = $this->createOrder();
        $this->adyenOrderPaymentMock = $this->createMock(AdyenOrderPayment::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->caseManagementMock = $this->createMock(CaseManagement::class);

        $paymentMethod = 'ADYEN_CC';
        $merchantReference = 'TestMerchant';
        $pspReference = 'ABCD1234GHJK5678';

        $this->notificationMock = $this->createConfiguredMock(Notification::class, [
            'getPspreference' => $pspReference,
            'getMerchantReference' => $merchantReference,
            'getPaymentMethod' => $paymentMethod,
            'isSuccessful' => true
        ]);
        $this->quoteMock = $this->createMock(Quote::class);
    }

    /**
     * @throws LocalizedException
     */
    public function testHandleWebhook(): void
    {
        // Set up expectations for mock objects
        $storeId = 1;
        $paymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getMethod' => 'adyen_cc'
        ]);
        $this->orderMock->method('getStoreId')->willReturn($storeId);
        $this->orderMock->method('getQuoteId')->willReturn('123');
        $this->quoteMock->method('getIsActive')->willReturn(false);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);

        $this->notificationMock->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $paymentMethodsHelperMock = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);


        $transitionState = PaymentStates::STATE_PAID;

        $cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $cartRepositoryMock->expects($this->once())->method('get')->willReturn($this->quoteMock);

        $handler = $this->createAuthorisationWebhookHandler(
            null,
            $this->orderHelperMock,
            null,
            null,
            null,
            null,
            null,
            $paymentMethodsHelperMock,
            $cartRepositoryMock
        );

        // Call the function to be tested
        $result = $handler->handleWebhook($this->orderMock, $this->notificationMock, $transitionState);

        // Assertions
        $this->assertInstanceOf(Order::class, $result);
    }

    public function isAutoCaptureProvider(): array
    {
        return [[true], [false]];
    }

    /**
     * @dataProvider isAutoCaptureProvider
     */
    public function testHandleSuccessfulAuthorisation($isAutoCapture): void
    {
        // Mock
        $orderAmount = 10.33;
        $this->adyenOrderPaymentMock->expects($this->once())
            ->method('createAdyenOrderPayment');
        $this->adyenOrderPaymentMock->expects($this->once())
            ->method('isFullAmountAuthorized')
            ->willReturn(true);

        $orderAmountCurrency = new AdyenAmountCurrency(
            $orderAmount,
            'EUR',
            null,
            null,
            $orderAmount
        );

        $mockChargedCurrency = $this->createConfiguredMock(ChargedCurrency::class, [
            'getOrderAmountCurrency' => $orderAmountCurrency
        ]);

        // Create mock instances for Order and Notification
        $paymentMock = $this->createMock(Order\Payment::class);
        $storeId = 1;
        $this->orderMock->method('getStoreId')->willReturn($storeId);
        $this->orderMock->method('getQuoteId')->willReturn('123');
        $this->orderMock->method('getPayment')->willReturn($paymentMock);
        $this->quoteMock->method('getIsActive')->willReturn(false);

        $this->orderHelperMock->expects($this->once())
            ->method('setPrePaymentAuthorized')->willReturn($this->orderMock);
        $this->orderHelperMock->expects($this->once())
            ->method('updatePaymentDetails');
        $this->orderHelperMock->expects($this->once())
            ->method('sendOrderMail');
        $this->orderHelperMock->expects($this->once())
            ->method('finalizeOrder')->willReturn($this->orderMock);

        $paymentMethodsMock = $this->createConfiguredMock(
            PaymentMethods::class,
            [
                'isAutoCapture' => true
            ]
        );

        $cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $cartRepositoryMock->expects($this->once())->method('get')->willReturn($this->quoteMock);

        $authorisationWebhookHandler = $this->createAuthorisationWebhookHandler(
            $this->adyenOrderPaymentMock,
            $this->orderHelperMock,
            null,
            null,
            null,
            null,
            null,
            $paymentMethodsMock,
            $cartRepositoryMock
        );

        // Invoke the private method
        $handleSuccessfulAuthorisationMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleSuccessfulAuthorisation'
        );

        $result = $handleSuccessfulAuthorisationMethod->invokeArgs(
            $authorisationWebhookHandler,
            [$this->orderMock, $this->notificationMock]
        );

        // Assert the result
        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * @throws ReflectionExceptionAlias
     */
    public function testHandleFailedAuthorisationAlreadyProcessed(): void
    {
        $this->orderMock->expects($this->atLeastOnce())
            ->method('getData')
            ->willReturnMap([
                ['adyen_notification_event_code', null, 'AUTHORISATION : TRUE'],
                ['adyen_notification_payment_captured', null, false]
            ]);

        // Create an instance of AuthorisationWebhookHandler
        $webhookHandler = $this->createAuthorisationWebhookHandler();

        $handleFailedAuthorisationMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleFailedAuthorisation'
        );

        // Call the private method directly and provide required parameters
        $result = $handleFailedAuthorisationMethod->invokeArgs(
            $webhookHandler,
            [$this->orderMock, $this->notificationMock]
        );

        // Assert the expected behavior based on the mocked logic and result
        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * @throws ReflectionExceptionAlias
     */
    public function testHandleFailedAuthorisation(): void
    {
        $orderPayment = $this->createMock(Order\Payment::class);
        $orderPayment->method('getMethod')->willReturn(AdyenCcConfigProvider::CODE);

        $this->orderMock->method('getPayment')->willReturn($orderPayment);

        $this->orderMock->expects($this->atLeastOnce())
            ->method('getData')
            ->willReturnMap([
                ['adyen_notification_event_code', null, false],
                ['adyen_notification_payment_captured', null, false]
            ]);

        // Create an instance of AuthorisationWebhookHandler
        $webhookHandler = $this->createAuthorisationWebhookHandler();

        $handleFailedAuthorisationMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleFailedAuthorisation'
        );

        // Call the private method directly and provide required parameters
        $result = $handleFailedAuthorisationMethod->invokeArgs(
            $webhookHandler,
            [$this->orderMock, $this->notificationMock]
        );

        // Assert the expected behavior based on the mocked logic and result
        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * @throws ReflectionExceptionAlias
     */
    public function testHandleAutoCapture(): void
    {
        // Set up expectations for the mocks
        $this->orderMock->expects($this->any())
            ->method('getPayment')
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->any())
            ->method('getConfig')
            ->willReturnSelf();

        // Create an instance of AuthorisationWebhookHandler
        $webhookHandler = $this->createAuthorisationWebhookHandler();

        // Use Reflection to access the private method
        $handleAutoCaptureMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleAutoCapture'
        );

        // Call the private method directly and provide required parameters
        $result = $handleAutoCaptureMethod->invokeArgs(
            $webhookHandler,
            [$this->orderMock, $this->notificationMock, true] // true indicates requireFraudManualReview
        );

        // Perform assertions on the result and expected behavior
        $this->assertInstanceOf(Order::class, $result);
    }

    public function testDisableQuote(): void
    {
        $this->orderMock->expects($this->any())->method('getPayment')->willReturn($this->orderMock);
        $this->orderMock->expects($this->any())->method('getConfig')->willReturnSelf();

        $this->orderMock->method('getQuoteId')->willReturn('123');

        $this->quoteMock->expects($this->any())->method('getIsActive')->willReturn(true);
        $this->quoteMock->expects($this->any())->method('setIsActive')->with(false);

        $cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $cartRepositoryMock->expects($this->once())->method('get')->with('123')->willReturn($this->quoteMock);
        $cartRepositoryMock->expects($this->once())->method('save')->with($this->quoteMock);

        $webhookHandler = $this->createAuthorisationWebhookHandler(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $cartRepositoryMock
        );

        $result = $webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_PAID
        );

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * @throws ReflectionExceptionAlias
     */
    public function testHandleManualCapture(): void
    {
        // Set up expectations for handleManualCapture private method
        $this->orderHelperMock->expects($this->never()) // Since the condition is true
        ->method('setPrePaymentAuthorized');

        $this->caseManagementMock->expects($this->once())
            ->method('markCaseAsPendingReview')
            ->with($this->orderMock, $this->notificationMock->getPspreference(), false);

        // Create an instance of AuthorisationWebhookHandler
        $webhookHandler = $this->createAuthorisationWebhookHandler(
            null,
            $this->orderHelperMock,
            $this->caseManagementMock
        );

        // Use Reflection to access the private method
        $handleManualCaptureMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleManualCapture'
        );

        // Call the private method directly and provide required parameters
        $result = $handleManualCaptureMethod->invokeArgs(
            $webhookHandler,
            [$this->orderMock, $this->notificationMock, true]
        );

        // Perform assertions on the result and expected behavior
        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * @throws ReflectionExceptionAlias
     */
    public function testCanCancelPayByLinkOrder(): void
    {
        // Create mocks for the required dependencies
        $paymentMock = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Configure the payment mock to return the payByLinkFailureCount
        $payByLinkFailureCount = 3; // Assuming the failure count is 3

        // Use willReturn() to mock chained method calls
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('payByLinkFailureCount')
            ->willReturn($payByLinkFailureCount);

        // Configure the order mock to return the payment mock
        $this->orderMock->method('getPayment')->willReturn($paymentMock);

        $paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('payByLinkFailureCount', $payByLinkFailureCount + 1);

        // Create an instance of AuthorisationWebhookHandler
        $webhookHandler = $this->createAuthorisationWebhookHandler();

        // Use Reflection to access the private method
        $canCancelPayByLinkOrderMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'canCancelPayByLinkOrder'
        );

        // Call the private method directly and provide required parameters
        $result = $canCancelPayByLinkOrderMethod->invokeArgs(
            $webhookHandler,
            [$this->orderMock, $this->notificationMock]
        );

        // Perform assertions on the result and expected behavior
        $this->assertIsBool($result);
    }
    public function testHandleWebhookFailedRoutesToFailedHandler(): void
    {
        $orderHelper = $this->createMock(OrderHelper::class);
        $cleanup = $this->createMock(CleanupAdditionalInformationInterface::class);
        $config = $this->createMock(Config::class);

        // Important: prevent null->getMethod()
        $paymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getMethod' => AdyenCcConfigProvider::CODE // non-PBL path
        ]);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);

        // Make sure we don't exit early
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);

        $this->orderMock->method('getData')->willReturnMap([
            ['adyen_notification_event_code', null, false],
            ['adyen_notification_payment_captured', null, false],
        ]);

        // Ensure we don't hit the "move from PAYMENT_REVIEW to NEW" path unexpectedly
        $this->orderMock->method('canCancel')->willReturn(true);
        $config->method('getNotificationsCanCancel')->willReturn(false);

        $cleanup->expects($this->once())->method('execute')->with($paymentMock);

        $orderHelper->expects($this->once())
            ->method('holdCancelOrder')
            ->with($this->orderMock, true)
            ->willReturn($this->orderMock);

        $handler = $this->createAuthorisationWebhookHandler(
            null,
            $orderHelper,
            null,
            null,
            null,
            $config,
            null,
            null,
            null,
            null,
            $cleanup
        );

        $result = $handler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_FAILED
        );

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * Full amount NOT authorized -> just adds webhook history comment (no finalize/invoice/cleanup)
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationNotFullAmountAuthorizedAddsHistoryCommentOnly(): void
    {
        $adyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        $adyenOrderPayment->expects($this->once())->method('createAdyenOrderPayment');
        $adyenOrderPayment->expects($this->once())
            ->method('isFullAmountAuthorized')
            ->willReturn(false);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->once())
            ->method('addWebhookStatusHistoryComment')
            ->with($this->orderMock, $this->notificationMock)
            ->willReturn($this->orderMock);

        $orderHelper->expects($this->never())->method('finalizeOrder');
        $orderHelper->expects($this->never())->method('sendOrderMail');

        $paymentMethods = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);

        $handler = $this->createAuthorisationWebhookHandler(
            $adyenOrderPayment,
            $orderHelper,
            null,
            null,
            null,
            null,
            null,
            $paymentMethods
        );

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleSuccessfulAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * Boleto -> order mail should NOT be sent.
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationBoletoDoesNotSendOrderMail(): void
    {
        $notification = $this->createConfiguredMock(Notification::class, [
            'getPaymentMethod' => 'adyen_boleto',
            'isSuccessful' => true,
            'getAdditionalData' => null
        ]);

        $adyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        $adyenOrderPayment->method('isFullAmountAuthorized')->willReturn(true);

        $paymentMethods = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('setPrePaymentAuthorized')->willReturn($this->orderMock);
        $orderHelper->expects($this->never())->method('sendOrderMail');
        $orderHelper->method('finalizeOrder')->willReturn($this->orderMock);

        $invoiceHelper = $this->createMock(Invoice::class);
        $invoiceHelper->expects($this->once())->method('createInvoice');

        $cleanup = $this->createMock(CleanupAdditionalInformationInterface::class);
        $cleanup->expects($this->once())->method('execute');

        $payment = $this->createMock(Order\Payment::class);
        $payment->expects($this->once())->method('setAmountAuthorized');
        $payment->expects($this->once())->method('setBaseAmountAuthorized');

        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('getEmailSent')->willReturn(false);
        $this->orderMock->method('getGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getBaseGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getQuoteId')->willReturn(null);

        $handler = $this->createAuthorisationWebhookHandler(
            $adyenOrderPayment,
            $orderHelper,
            $this->createMock(CaseManagement::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class),
            $invoiceHelper,
            $paymentMethods,
            $this->createMock(CartRepositoryInterface::class),
            null,
            $cleanup
        );

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleSuccessfulAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $notification]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * c_cash + create_shipment enabled -> createShipment called
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationCashCreatesShipmentWhenConfigured(): void
    {
        $notification = $this->createConfiguredMock(Notification::class, [
            'getPaymentMethod' => 'c_cash',
            'isSuccessful' => true,
            'getAdditionalData' => null
        ]);

        $adyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        $adyenOrderPayment->method('isFullAmountAuthorized')->willReturn(true);

        $paymentMethods = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);

        $config = $this->createMock(Config::class);
        $config->expects($this->once())
            ->method('getConfigData')
            ->with('create_shipment', 'adyen_cash', $this->anything())
            ->willReturn(true);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('setPrePaymentAuthorized')->willReturn($this->orderMock);
        $orderHelper->method('finalizeOrder')->willReturn($this->orderMock);
        $orderHelper->expects($this->once())->method('createShipment')->with($this->orderMock);

        $payment = $this->createMock(Order\Payment::class);
        $payment->expects($this->once())->method('setAmountAuthorized');
        $payment->expects($this->once())->method('setBaseAmountAuthorized');

        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('getStoreId')->willReturn(1);
        $this->orderMock->method('getEmailSent')->willReturn(true);
        $this->orderMock->method('getGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getBaseGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getQuoteId')->willReturn(null);

        $handler = $this->createAuthorisationWebhookHandler(
            $adyenOrderPayment,
            $orderHelper,
            $this->createMock(CaseManagement::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(AdyenLogger::class),
            $config,
            $this->createMock(Invoice::class),
            $paymentMethods,
            $this->createMock(CartRepositoryInterface::class),
            null,
            $this->createMock(CleanupAdditionalInformationInterface::class)
        );

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleSuccessfulAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $notification]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * Quote is active -> setIsActive(false) and save.
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationDeactivatesActiveQuote(): void
    {
        $adyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        $adyenOrderPayment->method('isFullAmountAuthorized')->willReturn(true);

        $paymentMethods = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('setPrePaymentAuthorized')->willReturn($this->orderMock);
        $orderHelper->method('finalizeOrder')->willReturn($this->orderMock);

        $payment = $this->createMock(Order\Payment::class);
        $payment->expects($this->once())->method('setAmountAuthorized');
        $payment->expects($this->once())->method('setBaseAmountAuthorized');

        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('getEmailSent')->willReturn(true);
        $this->orderMock->method('getGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getBaseGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getQuoteId')->willReturn(123);

        $quote = $this->createMock(Quote::class);
        $quote->method('getIsActive')->willReturn(true);
        $quote->expects($this->once())->method('setIsActive')->with(false);

        $cartRepo = $this->createMock(CartRepositoryInterface::class);
        $cartRepo->expects($this->once())->method('get')->with(123)->willReturn($quote);
        $cartRepo->expects($this->once())->method('save')->with($quote);

        $handler = $this->createAuthorisationWebhookHandler(
            $adyenOrderPayment,
            $orderHelper,
            $this->createMock(CaseManagement::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class),
            $this->createMock(Invoice::class),
            $paymentMethods,
            $cartRepo,
            null,
            $this->createMock(CleanupAdditionalInformationInterface::class)
        );

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleSuccessfulAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * cartRepository->get throws -> logger addAdyenNotification called.
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationQuoteDeactivationExceptionLogs(): void
    {
        $adyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        $adyenOrderPayment->method('isFullAmountAuthorized')->willReturn(true);

        $paymentMethods = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('setPrePaymentAuthorized')->willReturn($this->orderMock);
        $orderHelper->method('finalizeOrder')->willReturn($this->orderMock);

        $payment = $this->createMock(Order\Payment::class);
        $payment->expects($this->once())->method('setAmountAuthorized');
        $payment->expects($this->once())->method('setBaseAmountAuthorized');

        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('getEmailSent')->willReturn(true);
        $this->orderMock->method('getGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getBaseGrandTotal')->willReturn(10.0);
        $this->orderMock->method('getQuoteId')->willReturn(123);

        $cartRepo = $this->createMock(CartRepositoryInterface::class);
        $cartRepo->expects($this->once())
            ->method('get')
            ->willThrowException(new \Exception('boom'));

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Quote deactivation skipped'),
                $this->arrayHasKey('quoteId')
            );

        $handler = $this->createAuthorisationWebhookHandler(
            $adyenOrderPayment,
            $orderHelper,
            $this->createMock(CaseManagement::class),
            $this->createMock(SerializerInterface::class),
            $logger,
            $this->createMock(Config::class),
            $this->createMock(Invoice::class),
            $paymentMethods,
            $cartRepo,
            null,
            $this->createMock(CleanupAdditionalInformationInterface::class)
        );

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleSuccessfulAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * Failed auth: order already cancelled -> no cancel call
     * @throws ReflectionExceptionAlias
     */
    public function testHandleFailedAuthorisationOrderAlreadyCancelledDoesNothing(): void
    {
        $this->orderMock->method('isCanceled')->willReturn(true);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->never())->method('holdCancelOrder');

        $handler = $this->createAuthorisationWebhookHandler(null, $orderHelper);

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleFailedAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * PayByLink: failure count below MAX -> notification saved and no cancellation
     * @throws ReflectionExceptionAlias
     */
    public function testHandleFailedAuthorisationPayByLinkBelowMaxDoesNotCancel(): void
    {
        $payment = $this->createMock(Order\Payment::class);
        $payment->method('getMethod')->willReturn(AdyenPayByLinkConfigProvider::CODE);
        $payment->method('getAdditionalInformation')->with('payByLinkFailureCount')->willReturn(1);
        $payment->expects($this->once())->method('setAdditionalInformation');

        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);

        $this->orderMock->method('getData')->willReturnMap([
            ['adyen_notification_event_code', null, false],
            ['adyen_notification_payment_captured', null, false],
        ]);

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($this->notificationMock);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->never())->method('holdCancelOrder');

        $handler = $this->createAuthorisationWebhookHandler(
            null,
            $orderHelper,
            null,
            null,
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class),
            null,
            null,
            null,
            $repo
        );

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleFailedAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * PayByLink: failure count reaches MAX -> proceeds to cancel
     * @throws ReflectionExceptionAlias
     */
    public function testHandleFailedAuthorisationPayByLinkAtMaxCancels(): void
    {
        $payment = $this->createMock(Order\Payment::class);
        $payment->method('getMethod')->willReturn(AdyenPayByLinkConfigProvider::CODE);
        $payment->method('getAdditionalInformation')->with('payByLinkFailureCount')->willReturn(
            AdyenPayByLinkConfigProvider::MAX_FAILURE_COUNT - 1
        );
        $payment->expects($this->once())->method('setAdditionalInformation');

        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('isCanceled')->willReturn(false);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);

        $this->orderMock->method('getData')->willReturnMap([
            ['adyen_notification_event_code', null, false],
            ['adyen_notification_payment_captured', null, false],
        ]);

        $cleanup = $this->createMock(CleanupAdditionalInformationInterface::class);
        $cleanup->expects($this->once())->method('execute');

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->expects($this->once())->method('holdCancelOrder')->willReturn($this->orderMock);

        $handler = $this->createAuthorisationWebhookHandler(
            null,
            $orderHelper,
            null,
            null,
            null,
            $this->createMock(Config::class),
            null,
            null,
            null,
            $this->createMock(AdyenNotificationRepositoryInterface::class),
            $cleanup
        );

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleFailedAuthorisation');
        $result = $method->invokeArgs($handler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * @throws ReflectionExceptionAlias
     */
    private function getPrivateMethod(string $className, string $methodName): ReflectionMethod
    {
        $reflectionClass = new ReflectionClass($className);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    protected function createAuthorisationWebhookHandler(
        $mockAdyenOrderPayment = null,
        $mockOrderHelper = null,
        $mockCaseManagementHelper = null,
        $mockSerializer = null,
        $mockAdyenLogger = null,
        $mockConfigHelper = null,
        $mockInvoiceHelper = null,
        $mockPaymentMethodsHelper = null,
        $mockCartRepositoryMock = null,
        $adyenNotificationRepositoryMock = null,
        $cleanupAdditionalInformation = null
    ): AuthorisationWebhookHandler {
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

        if (is_null($mockConfigHelper)) {
            $mockConfigHelper = $this->createMock(Config::class);
        }

        if (is_null($mockInvoiceHelper)) {
            $mockInvoiceHelper = $this->createMock(Invoice::class);
        }

        if (is_null($mockPaymentMethodsHelper)) {
            $mockPaymentMethodsHelper = $this->createMock(PaymentMethods::class);
        }

        if (is_null($mockCartRepositoryMock)) {
            $mockCartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        }

        if (is_null($adyenNotificationRepositoryMock)) {
            $adyenNotificationRepositoryMock = $this->createMock(AdyenNotificationRepositoryInterface::class);
        }

        if (is_null($cleanupAdditionalInformation)) {
            $cleanupAdditionalInformation = $this->createMock(CleanupAdditionalInformationInterface::class);
        }

        return new AuthorisationWebhookHandler(
            $mockAdyenOrderPayment,
            $mockOrderHelper,
            $mockCaseManagementHelper,
            $mockSerializer,
            $mockAdyenLogger,
            $mockConfigHelper,
            $mockInvoiceHelper,
            $mockPaymentMethodsHelper,
            $mockCartRepositoryMock,
            $adyenNotificationRepositoryMock,
            $cleanupAdditionalInformation
        );
    }
}
