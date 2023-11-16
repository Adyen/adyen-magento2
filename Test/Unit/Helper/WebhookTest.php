<?php
namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Exception\AdyenWebhookException;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook\WebhookHandlerFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Notification as WebhookNotification;
use Adyen\Webhook\PaymentStates;
use Adyen\Webhook\Processor\ProcessorFactory;
use DateTime;
use Exception;
use ReflectionMethod;
use ReflectionClass;
use ReflectionException as ReflectionExceptionAlias;

class WebhookTest extends AbstractAdyenTestCase
{
    private $webhook;
    private $orderRepository;
    private $serializer;
    private $timezone;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
    }

    public function testProcessNotificationWithInvalidMerchantReference()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn(null);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Invalid merchant reference'));

        $webhookHandler = $this->createWebhook(null,null,null,null,null,$logger,null,null,null);

        $result = $webhookHandler->processNotification($notification);

        $this->assertFalse($result);
    }

    public function testProcessNotificationWithOrderNotFound()
    {
        $merchantReference = 'TestMerchant'; // Replace with a merchant reference that does not exist
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn($merchantReference);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getOrderByIncrementId')->with($merchantReference)->willReturn(null);

        $logger = $this->createMock(AdyenLogger::class);

        $webhookHandler = $this->createWebhook(null, null, null, null, null, $logger, null, $orderHelper, null);

        // Additional setup if needed before calling the method

        $result = $webhookHandler->processNotification($notification);

        // Assertions for the unsuccessful processing
        $this->assertFalse($result);
        // Add more assertions as needed to validate the error handling logic
    }

    public function testGetCurrentStateWithValidOrderState()
    {
        // Use Reflection to test private method getCurrentState
        $method = $this->getPrivateMethod(
            Webhook::class,
            'getCurrentState'
        );

        $orderState = Order::STATE_NEW;
        $webhookHandler = $this->createWebhook(null,null,null,null,null,null,null,null,null);

        $currentState = $method->invokeArgs($webhookHandler, [$orderState]);

        $this->assertEquals(Webhook::WEBHOOK_ORDER_STATE_MAPPING[$orderState], $currentState);
    }

    public function testProcessNotificationWithUnsupportedWebhook()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn('TestMerchant');
        $notification->method('getEventCode')->willReturn('ADYEN');

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Unsupported webhook notification'));

        $webhookHandler = $this->createWebhook(null, null, null, null, null, $logger, null, null, null);

        $result = $webhookHandler->processNotification($notification);

        $this->assertFalse($result);
    }


    public function testProcessNotificationTryBlock()
    {
        // Mock necessary objects
        $notification = $this->createMock(Notification::class);
        $order = $this->createMock(Order::class);
        $orderHelper = $this->createMock(OrderHelper::class);
        $orderRepository = $this->createMock(OrderRepository::class);
        $logger = $this->createMock(AdyenLogger::class);

        // Create a partial mock for the Webhook class
        $webhookHandler = $this->getMockBuilder(Webhook::class)
            ->setConstructorArgs([
                $orderHelper,
                $this->createMock(SerializerInterface::class),
                $this->createMock(TimezoneInterface::class),
                $this->createMock(ConfigHelper::class),
                $this->createMock(ChargedCurrency::class),
                $logger,
                $this->createMock(WebhookHandlerFactory::class),
                $orderHelper,
                $orderRepository,
            ])
            ->onlyMethods([
                'declareVariables',
                'addNotificationDetailsHistoryComment',
                'updateAdyenAttributes',
                'getCurrentState',
                'getTransitionState',
                'webhookHandlerFactory',
                'updateNotification',
            ])
            ->getMock();

        // Set up expectations for the order helper and repository
        $orderHelper->expects($this->once())
            ->method('getOrderByIncrementId')
            ->willReturn($order);
        $orderRepository->expects($this->once())
            ->method('save')
            ->with($order);

        // Set up expectations for the logger
        $logger->expects($this->at(0))
            ->method('addAdyenNotification')
            ->with($this->stringContains('Processing'));
        $logger->expects($this->at(1))
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Notification was processed'),
                $this->callback(function ($context) {
                    return isset($context['pspReference']) && isset($context['merchantReference']);
                })
            );

        // Set up expectations for the mocked methods within the try block
        $webhookHandler->expects($this->once())
            ->method('declareVariables');
        $webhookHandler->expects($this->once())
            ->method('addNotificationDetailsHistoryComment')
            ->willReturn($order); // Adjust based on your actual implementation
        $webhookHandler->expects($this->once())
            ->method('updateAdyenAttributes')
            ->willReturn($order); // Adjust based on your actual implementation
        $webhookHandler->expects($this->once())
            ->method('getCurrentState')
            ->willReturn('mock-current-state');

        // Call the method under test
        $result = $webhookHandler->processNotification($notification);

        // Assertions
        $this->assertTrue($result);
        // Add more assertions as needed to validate the processing logic
    }

    public function testProcessNotificationWithOrderFoundAndException()
    {
        // Mock necessary objects
        $notification = $this->createMock(Notification::class);
        $order = $this->createMock(Order::class);
        $orderHelper = $this->createMock(OrderHelper::class);
        $orderRepository = $this->createMock(OrderRepository::class);
        $logger = $this->createMock(AdyenLogger::class);

        // Set up expectations for the order helper and repository
        $orderHelper->expects($this->once())
            ->method('getOrderByIncrementId')
            ->willReturn($order);
        $orderRepository->expects($this->once())
            ->method('save')
            ->with($order);

        // Set up expectations for the logger
        $logger->expects($this->at(0))
            ->method('addAdyenNotification')
            ->with($this->stringContains('Processing'));
        $logger->expects($this->at(1))
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Notification was processed'),
                $this->callback(function ($context) {
                    return isset($context['pspReference']) && isset($context['merchantReference']);
                })
            );

        // Mock the try block to throw an exception
        $webhookHandler = $this->getMockBuilder(Webhook::class)
            ->setConstructorArgs([
                null, null, null, null, null, $logger, null, $orderHelper, $orderRepository
            ])
            ->onlyMethods(['declareVariables', 'addNotificationDetailsHistoryComment', 'updateAdyenAttributes'])
            ->getMock();

        $webhookHandler->expects($this->once())
            ->method('declareVariables')
            ->willThrowException(new Exception('Simulated exception'));

        // Mock necessary data for the notification
        $notification->method('getMerchantReference')->willReturn('mock-merchant-reference');
        $notification->method('getPspreference')->willReturn('mock-psp-reference');

        // Additional setup if needed before calling the method

        // Call the method under test
        $result = $webhookHandler->processNotification($notification);

        // Assertions
        $this->assertFalse($result); // Since an exception is thrown, the result should be false
        // Add more assertions as needed to validate the processing logic in case of an exception
    }

    public function testProcessNotificationWithInvalidEventCode()
    {
        $merchantReference = 'TestMerchant';
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn($merchantReference);
        $notification->method('getEventCode')->willReturn('AUTHORISATION');

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Unsupported webhook notification: AUTHORISATION'));

        $webhookHandler = $this->createWebhook(null, null, null, null, null, $logger, null, null, null);

        $result = $webhookHandler->processNotification($notification);

        $this->assert($result);
    }

    public function testGetTransitionStateWithUnsupportedEventCode()
    {
        $eventCode = 'AUTHORISATION';
        $currentOrderState = 'STATE_NEW';

        $notification = $this->createMock(Notification::class);
        $notification->method('getEventCode')->willReturn($eventCode);
        $notification->method('getSuccess')->willReturn('SUCCESS');

        $webhookHandler = $this->createWebhook(null, null, null, null, null, null, null, null, null);

        $method = $this->getPrivateMethod(Webhook::class, 'getTransitionState');
        $result = $method->invokeArgs($webhookHandler, [$notification, $currentOrderState]);

        $this->assertEquals(PaymentStates::STATE_NEW, $result);
    }


    protected function createWebhook(
        $mockAdyenHelper = null,
        $mockSerializer = null,
        $mockTimezone = null,
        $mockConfigHelper = null,
        $mockChargedCurrency = null,
        $mockLogger = null,
        $mockWebhookHandlerFactory = null,
        $mockOrderHelper = null,
        $mockOrderRepository = null
    ): Webhook
    {
        if (is_null($mockAdyenHelper)) {
            $mockAdyenHelper = $this->createMock(Data::class);
        }
        if (is_null($mockSerializer)) {
            $mockSerializer = $this->createMock(SerializerInterface::class);
        }
        if (is_null($mockTimezone)) {
            $mockTimezone = $this->createMock(TimezoneInterface::class);
        }
        if (is_null($mockConfigHelper)) {
            $mockConfigHelper = $this->createMock(ConfigHelper::class);
        }
        if (is_null($mockChargedCurrency)) {
            $mockChargedCurrency = $this->createMock(ChargedCurrency::class);
        }
        if (is_null($mockLogger)) {
            $mockLogger = $this->createMock(AdyenLogger::class);
        }
        if (is_null($mockWebhookHandlerFactory)) {
            $mockWebhookHandlerFactory = $this->createMock(WebhookHandlerFactory::class);
        }
        if (is_null($mockOrderHelper)) {
            $mockOrderHelper = $this->createMock(OrderHelper::class);
        }
        if (is_null($mockOrderRepository)) {
            $mockOrderRepository = $this->createMock(OrderRepository::class);
        }
        return new Webhook(
            $mockAdyenHelper,
            $mockSerializer,
            $mockTimezone,
            $mockConfigHelper,
            $mockChargedCurrency,
            $mockLogger,
            $mockWebhookHandlerFactory,
            $mockOrderHelper,
            $mockOrderRepository
        );
    }

    private function getPrivateMethod(string $className, string $methodName): ReflectionMethod
    {
        $reflectionClass = new ReflectionClass($className);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    // Add more test cases for other private methods and scenarios
}
