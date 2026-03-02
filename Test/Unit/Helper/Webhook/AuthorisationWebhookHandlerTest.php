<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Model\AuthorizationHandler;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Model\ResourceModel\Order\Payment as AdyenOrderPaymentResourceModel;
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
use Adyen\Payment\Helper\Config;
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
    private OrderHelper|MockObject $orderHelperMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private Config|MockObject $configHelperMock;
    private CartRepositoryInterface|MockObject $cartRepositoryMock;
    private AdyenNotificationRepositoryInterface|MockObject $notificationRepositoryMock;
    private CleanupAdditionalInformationInterface|MockObject $cleanupAdditionalInformationMock;
    private AuthorizationHandler|MockObject $authorizationHandlerMock;
    private SerializerInterface|MockObject $serializerMock;
    private AdyenOrderPaymentResourceModel|MockObject $adyenOrderPaymentResourceModelMock;
    private AuthorisationWebhookHandler $authorisationWebhookHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderMock = $this->createMock(Order::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->notificationRepositoryMock = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $this->cleanupAdditionalInformationMock = $this->createMock(CleanupAdditionalInformationInterface::class);
        $this->authorizationHandlerMock = $this->createMock(AuthorizationHandler::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->adyenOrderPaymentResourceModelMock = $this->createMock(AdyenOrderPaymentResourceModel::class);

        $paymentMethod = 'ADYEN_CC';
        $merchantReference = 'TestMerchant';
        $pspReference = 'ABCD1234GHJK5678';

        $this->notificationMock = $this->createConfiguredMock(Notification::class, [
            'getPspreference' => $pspReference,
            'getMerchantReference' => $merchantReference,
            'getPaymentMethod' => $paymentMethod,
            'getAmountValue' => 1000,
            'getAmountCurrency' => 'EUR',
            'getAdditionalData' => null,
            'isSuccessful' => true
        ]);

        $this->quoteMock = $this->createMock(Quote::class);

        $this->authorisationWebhookHandler = new AuthorisationWebhookHandler(
            $this->orderHelperMock,
            $this->adyenLoggerMock,
            $this->configHelperMock,
            $this->cartRepositoryMock,
            $this->notificationRepositoryMock,
            $this->cleanupAdditionalInformationMock,
            $this->authorizationHandlerMock,
            $this->serializerMock,
            $this->adyenOrderPaymentResourceModelMock
        );
    }

    /**
     * When no order payment exists yet, AuthorizationHandler::execute is called as fallback.
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationCallsAuthorizationHandler(): void
    {
        $paymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 1
        ]);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);
        $this->orderMock->method('getQuoteId')->willReturn('123');

        $this->adyenOrderPaymentResourceModelMock->method('getOrderPaymentDetails')->willReturn([]);

        $this->authorizationHandlerMock->expects($this->once())
            ->method('execute')
            ->willReturn($this->orderMock);

        $this->orderHelperMock->expects($this->once())
            ->method('addWebhookStatusHistoryComment')
            ->with($this->orderMock, $this->notificationMock);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with('123')
            ->willReturn($this->quoteMock);

        $method = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleSuccessfulAuthorisation'
        );

        $result = $method->invokeArgs($this->authorisationWebhookHandler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * When order payment already exists, AuthorizationHandler::execute is NOT called.
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationSkipsAuthorizationHandlerWhenPaymentExists(): void
    {
        $paymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 1
        ]);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);
        $this->orderMock->method('getQuoteId')->willReturn(null);

        $this->adyenOrderPaymentResourceModelMock->method('getOrderPaymentDetails')
            ->willReturn([['entity_id' => 10]]);

        $this->authorizationHandlerMock->expects($this->never())->method('execute');

        $this->orderHelperMock->expects($this->once())
            ->method('addWebhookStatusHistoryComment');

        $method = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleSuccessfulAuthorisation'
        );

        $result = $method->invokeArgs($this->authorisationWebhookHandler, [$this->orderMock, $this->notificationMock]);

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

        $handleFailedAuthorisationMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleFailedAuthorisation'
        );

        // Call the private method directly and provide required parameters
        $result = $handleFailedAuthorisationMethod->invokeArgs(
            $this->authorisationWebhookHandler,
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

        $handleFailedAuthorisationMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'handleFailedAuthorisation'
        );

        // Call the private method directly and provide required parameters
        $result = $handleFailedAuthorisationMethod->invokeArgs(
            $this->authorisationWebhookHandler,
            [$this->orderMock, $this->notificationMock]
        );

        // Assert the expected behavior based on the mocked logic and result
        $this->assertInstanceOf(Order::class, $result);
    }

    public function testDisableQuote(): void
    {
        $paymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 1
        ]);
        $this->orderMock->method('getPayment')->willReturn($paymentMock);
        $this->orderMock->method('getQuoteId')->willReturn('123');

        $this->quoteMock->expects($this->any())->method('getIsActive')->willReturn(true);
        $this->quoteMock->expects($this->any())->method('setIsActive')->with(false);

        $this->cartRepositoryMock->expects($this->once())->method('get')->with('123')->willReturn($this->quoteMock);
        $this->cartRepositoryMock->expects($this->once())->method('save')->with($this->quoteMock);

        $this->authorizationHandlerMock->expects($this->once())
            ->method('execute')
            ->willReturn($this->orderMock);

        $result = $this->authorisationWebhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_PAID
        );

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

        // Use Reflection to access the private method
        $canCancelPayByLinkOrderMethod = $this->getPrivateMethod(
            AuthorisationWebhookHandler::class,
            'canCancelPayByLinkOrder'
        );

        // Call the private method directly and provide required parameters
        $result = $canCancelPayByLinkOrderMethod->invokeArgs(
            $this->authorisationWebhookHandler,
            [$this->orderMock, $this->notificationMock]
        );

        // Perform assertions on the result and expected behavior
        $this->assertIsBool($result);
    }
    public function testHandleWebhookFailedRoutesToFailedHandler(): void
    {
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
        $this->configHelperMock->method('getNotificationsCanCancel')->willReturn(false);

        $this->cleanupAdditionalInformationMock->expects($this->once())->method('execute')->with($paymentMock);

        $this->orderHelperMock->expects($this->once())
            ->method('holdCancelOrder')
            ->with($this->orderMock, true)
            ->willReturn($this->orderMock);

        $result = $this->authorisationWebhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            PaymentStates::STATE_FAILED
        );

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * Quote is active -> setIsActive(false) and save.
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationDeactivatesActiveQuote(): void
    {
        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 1
        ]);
        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('getQuoteId')->willReturn(123);

        $quote = $this->createMock(Quote::class);
        $quote->method('getIsActive')->willReturn(true);
        $quote->expects($this->once())->method('setIsActive')->with(false);

        $this->cartRepositoryMock->expects($this->once())->method('get')->with(123)->willReturn($quote);
        $this->cartRepositoryMock->expects($this->once())->method('save')->with($quote);

        $this->authorizationHandlerMock->expects($this->once())
            ->method('execute')
            ->willReturn($this->orderMock);

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleSuccessfulAuthorisation');
        $result = $method->invokeArgs($this->authorisationWebhookHandler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * cartRepository->get throws -> logger addAdyenNotification called.
     * @throws ReflectionExceptionAlias
     */
    public function testHandleSuccessfulAuthorisationQuoteDeactivationExceptionLogs(): void
    {
        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 1
        ]);
        $this->orderMock->method('getPayment')->willReturn($payment);
        $this->orderMock->method('getQuoteId')->willReturn(123);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->willThrowException(new \Exception('boom'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Quote deactivation skipped'),
                $this->arrayHasKey('quoteId')
            );

        $this->authorizationHandlerMock->expects($this->once())
            ->method('execute')
            ->willReturn($this->orderMock);

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleSuccessfulAuthorisation');
        $result = $method->invokeArgs($this->authorisationWebhookHandler, [$this->orderMock, $this->notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * Failed auth: order already cancelled -> no cancel call
     * @throws ReflectionExceptionAlias
     */
    public function testHandleFailedAuthorisationOrderAlreadyCancelledDoesNothing(): void
    {
        $this->orderMock->method('isCanceled')->willReturn(true);

        $this->orderHelperMock->expects($this->never())->method('holdCancelOrder');

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleFailedAuthorisation');
        $result = $method->invokeArgs($this->authorisationWebhookHandler, [$this->orderMock, $this->notificationMock]);

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

        $this->notificationRepositoryMock->expects($this->once())->method('save')->with($this->notificationMock);

        $this->orderHelperMock->expects($this->never())->method('holdCancelOrder');

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleFailedAuthorisation');
        $result = $method->invokeArgs($this->authorisationWebhookHandler, [$this->orderMock, $this->notificationMock]);

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

        $this->cleanupAdditionalInformationMock->expects($this->once())->method('execute');

        $this->orderHelperMock->expects($this->once())->method('holdCancelOrder')->willReturn($this->orderMock);

        $method = $this->getPrivateMethod(AuthorisationWebhookHandler::class, 'handleFailedAuthorisation');
        $result = $method->invokeArgs($this->authorisationWebhookHandler, [$this->orderMock, $this->notificationMock]);

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
}
