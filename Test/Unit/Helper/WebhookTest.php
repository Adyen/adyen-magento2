<?php
namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Helper\Webhook\AuthorisationWebhookHandler;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook\WebhookHandlerFactory;
use Adyen\Payment\Logger\AdyenLogger;
use ReflectionMethod;
use ReflectionClass;

class WebhookTest extends AbstractAdyenTestCase
{
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

        $result = $webhookHandler->processNotification($notification);

        // Assertions for the unsuccessful processing
        $this->assertFalse($result);
    }

    public function testGetCurrentStateWithValidOrderState()
    {
        $method = $this->getPrivateMethod(
            Webhook::class,
            'getCurrentState'
        );

        $orderState = Order::STATE_NEW;
        $webhookHandler = $this->createWebhook(null,null,null,null,null,null,null,null,null);

        $currentState = $method->invokeArgs($webhookHandler, [$orderState]);

        $this->assertEquals(Webhook::WEBHOOK_ORDER_STATE_MAPPING[$orderState], $currentState);
    }

    public function testProcessNotificationForInvalidDataException()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn('TestMerchant');
        $notification->method('getEventCode')->willReturn('AUTHORISATION : FALSE');
        $notification->method('getEntityId')->willReturn('1234');
        $notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $notification->method('getPaymentMethod')->willReturn('ADYEN_CC');

        // Mocking Order and other dependencies
        $payment = $this->createMock(Payment::class);
        $order = $this->createMock(Order::class);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getIncrementId')->willReturn(123);
        $order->method('getId')->willReturn(123);
        $order->method('getStatus')->willReturn('processing');
        $order->method('getPayment')->willReturn($payment);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('payment_method', $notification->getPaymentMethod());

        $logger = $this->createMock(AdyenLogger::class);
        $logger->method('getOrderContext')->with($order);

        // Mock the WebhookHandlerFactory and WebhookHandler
        $webhookHandlerFactory = $this->createMock(WebhookHandlerFactory::class);

        // Partially mock the Webhook class
        $webhookHandler = $this->getMockBuilder(Webhook::class)
            ->setConstructorArgs([
                $this->createMock(Data::class),
                $this->createMock(SerializerInterface::class),
                $this->createMock(TimezoneInterface::class),
                $this->createMock(ConfigHelper::class),
                $this->createMock(ChargedCurrency::class),
                $logger,
                $webhookHandlerFactory,
                $orderHelper,
                $this->createMock(OrderRepository::class)
            ])
            ->onlyMethods([
                'updateNotification',
                'addNotificationDetailsHistoryComment',
                'updateAdyenAttributes',
                'getCurrentState',
                'getTransitionState',
                'handleNotificationError'
            ])
            ->getMock();

        // Expect the method updateAdyenAttributes to be called with the mocked $order and $notification arguments
        $updateAdyenAttributes = $this->getPrivateMethod(
            Webhook::class,
            'updateAdyenAttributes'
        );
        $updateAdyenAttributes->invokeArgs($webhookHandler, [$order,$notification]);

        // Now, you can update the private method with the mocked $order and $notification
        $updateNotification = $this->getPrivateMethod(
            Webhook::class,
            'updateNotification'
        );
        $updateNotification->invokeArgs($webhookHandler, [$notification, true, false]);

        $result = $webhookHandler->processNotification($notification);

        $this->assertFalse($result);
    }

    public function testAddNotificationDetailsHistoryComment()
    {
        // Mock necessary dependencies
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $webhook = $this->createWebhook(null,null,null,null,null,null,null,null,null);

        $method = $this->getPrivateMethod(
            Webhook::class,
            'addNotificationDetailsHistoryComment'
        );

        // Call the private method
        $result = $method->invokeArgs($webhook, [$orderMock, $notificationMock]);

        $this->assertInstanceOf(Order::class, $result, 'The function did not return an instance of Order as expected.');
    }

    public function testGetTransitionState()
    {
        // Mock necessary dependencies
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create an instance of your class
        $webhook = $this->createWebhook(null, null, null, null, null, null, null, null, null);

        // Use reflection to make the private method accessible
        $method = new ReflectionMethod(Webhook::class, 'getTransitionState');
        $method->setAccessible(true);

        // Set up expectations for the mocked objects
        $notificationMock->expects($this->once())
            ->method('getEventCode')
            ->willReturn('AUTHORISATION');

        $notificationMock->expects($this->once())
            ->method('getSuccess')
            ->willReturn('SUCCESS');

        $orderState = Order::STATE_NEW;

        // Call the private method
        $result = $method->invokeArgs($webhook, [$notificationMock, $orderState]);

        // Assertions based on your logic
        $this->assertNotEquals(
            'STATE_NEW',
            $result,
            sprintf('The transition state is not as expected. Actual result: %s', $result)
        );
    }

    public function testUpdateAdyenAttributes()
    {
        // Mock necessary dependencies
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $loggerMock = $this->getMockBuilder(AdyenLogger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $serializerMock = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create an instance of your class
        $webhook = $this->createWebhook(null, $serializerMock, null, null, null, $loggerMock, null, null, null);

        // Set up expectations for the mocked objects
        $notificationMock->expects($this->once())
            ->method('getEventCode')
            ->willReturn(Notification::AUTHORISATION);

        $notificationMock->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(false);

        $orderMock->expects($this->once())
            ->method('getData')
            ->with('adyen_notification_event_code')
            ->willReturn('AUTHORISATION : FALSE');

        $orderMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $updateOrderPaymentWithAdyenAttributes = $this->getPrivateMethod(
            Webhook::class,
            'updateOrderPaymentWithAdyenAttributes'
        );

        $additionalData = array();
        $updateOrderPaymentWithAdyenAttributes->invokeArgs($webhook, [$paymentMock, $notificationMock, $additionalData]);

        $loggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                'Updating the Adyen attributes of the order',
                [
                    'pspReference' => $notificationMock->getPspreference(), // Provide the expected value,
                    'merchantReference' => $notificationMock->getMerchantReference(), // Provide the expected value
                ]
            );

        // Use reflection to make the private method accessible
        $updateAdyenAttributes = $this->getPrivateMethod(
            Webhook::class,
            'updateAdyenAttributes'
        );

        // Call the private method
        $result = $updateAdyenAttributes->invokeArgs($webhook, [$orderMock, $notificationMock]);

        // Assertions based on your logic
        $this->assertInstanceOf(Order::class, $result, 'The updateAdyenAttributes method did not return an Order instance.');
    }

    public function testUpdateOrderPaymentWithAdyenAttributes()
    {
        // Mock necessary dependencies
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMock = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create an instance of your class
        $webhook = $this->createWebhook(null, null, null, null, null, null, null, null, null);

        // Use reflection to make the private method accessible
        $updateOrderPaymentWithAdyenAttributes = $this->getPrivateMethod(
            Webhook::class,
            'updateOrderPaymentWithAdyenAttributes'
        );

        // Set up expectations for the mocked objects
        $additionalData = [
            'avsResult' => 'some_avs_result',
            'cvcResult' => 'some_cvc_result',
            'totalFraudScore' => 'some_fraud_score',
            'cardSummary' => 'some_card_summary',
            'refusalReasonRaw' => 'some_refusal_reason',
            'acquirerReference' => 'some_acquirer_reference',
            'authCode' => 'some_auth_code',
            'cardBin' => 'some_card_bin',
            'expiryDate' => 'some_expiry_date',
            'issuerCountry' => 'some_issuer_country',
        ];

        $notificationMock->expects($this->once())
            ->method('getReason')
            ->willReturn('REASON');

        $notificationMock->method('getPspreference')
            ->willReturn('ABCD1234GHJK5678');

        $retrieveLast4DigitsFromReason = $this->getPrivateMethod(
            Webhook::class,
            'retrieveLast4DigitsFromReason'
        );
        $retrieveLast4DigitsFromReason->invokeArgs($webhook, [$notificationMock->getReason()]);


        // Call the private method
        $updateOrderPaymentWithAdyenAttributes->invokeArgs($webhook, [$paymentMock, $notificationMock, $additionalData]);

        $this->assertNotEquals('ABCD1234GHJK5678', $paymentMock->getAdyenPspReference());
        $this->assertNotEquals('some_psp_reference', $paymentMock->getAdditionalInformation('pspReference'));
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
