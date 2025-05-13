<?php
namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Helper\Webhook\WebhookHandlerInterface;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\MethodInterface;
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
use Adyen\Payment\Exception\AdyenWebhookException;

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

        $webhookHandler = $this->createWebhookHelperClass(
            null,
            null,
            null,
            null,
            null,
            $logger
        );

        $result = $webhookHandler->processNotification($notification);

        $this->assertFalse($result);
    }

    public function testProcessNotificationWithOrderNotFound()
    {
        $merchantReference = 'TestMerchant';
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn($merchantReference);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getOrderByIncrementId')->with($merchantReference)->willReturn(null);

        $logger = $this->createMock(AdyenLogger::class);

        $webhookHandler = $this->createWebhookHelperClass(
            null,
            null,
            null,
            null,
            null,
            $logger,
            null,
            $orderHelper
        );

        $result = $webhookHandler->processNotification($notification);

        $this->assertFalse($result);
    }

    public function testGetCurrentStateWithValidOrderState()
    {
        $orderState = Order::STATE_NEW;
        $webhookHandler = $this->createWebhookHelperClass();
        $currentState = $this->invokeMethod($webhookHandler,'getCurrentState',[$orderState]);
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

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);

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

        $webhookHandlerFactory = $this->createMock(WebhookHandlerFactory::class);

        $webhook = $this->createWebhookHelperClass(
            null,
            null,
            null,
            null,
            null,
            $logger,
            $webhookHandlerFactory,
            $orderHelper
        );

        $this->assertFalse($webhook->processNotification($notification));
    }

    public function testAddNotificationDetailsHistoryComment()
    {
        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);

        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $webhook = $this->createWebhookHelperClass();

        $result = $this->invokeMethod($webhook,'addNotificationDetailsHistoryComment',[$orderMock, $notificationMock]);

        $this->assertInstanceOf(Order::class, $result, 'The function did not return an instance of Order as expected.');
    }

    public function testGetTransitionState()
    {
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $webhook = $this->createWebhookHelperClass();

        $method = new ReflectionMethod(Webhook::class, 'getTransitionState');
        $method->setAccessible(true);

        $notificationMock->expects($this->once())
            ->method('getEventCode')
            ->willReturn('AUTHORISATION');

        $notificationMock->expects($this->once())
            ->method('getSuccess')
            ->willReturn('SUCCESS');

        $orderState = Order::STATE_NEW;

        $result = $method->invokeArgs($webhook, [$notificationMock, $orderState]);

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
        $orderMock->method('getData')->willReturnSelf();

        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $loggerMock = $this->getMockBuilder(AdyenLogger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $serializerMock = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderRepositoryMock = $this->createMock(OrderRepository::class);
        $orderRepositoryMock->method('get')->willReturn($orderMock);

        $webhook = $this->createWebhookHelperClass(
            null,
            $serializerMock,
            null,
            null,
            null,
            $loggerMock,
            null,
            null,
            $orderRepositoryMock
        );

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

        $loggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                'Updating the Adyen attributes of the order',
                [
                    'pspReference' => $notificationMock->getPspreference(), // Provide the expected value,
                    'merchantReference' => $notificationMock->getMerchantReference(), // Provide the expected value
                ]
            );

        $result = $this->invokeMethod($webhook,'updateAdyenAttributes',[$orderMock, $notificationMock]);

        $this->assertInstanceOf(Order::class, $result, 'The updateAdyenAttributes method did not return an Order instance.');
    }

    public function testProcessNotificationWithSuccess()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn('TestMerchant');
        $notification->method('getEventCode')->willReturn('AUTHORISATION');
        $notification->method('getSuccess')->willReturn('SUCCESS');
        $notification->method('isSuccessful')->willReturn(true);
        $notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $order = $this->createMock(Order::class);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $mockWebhookHandlerFactory = $this->createMock(WebhookHandlerFactory::class);
        $webhookHandlerInterface = $this->createMock(WebhookHandlerInterface::class);
        $webhookHandlerInterface->method('handleWebhook')->willReturn($order);
        $mockWebhookHandlerFactory->method('create')->willReturn($webhookHandlerInterface);

        $payment->expects($this->any())->method('getData')->will(
            $this->returnCallback(function($key) {
                $array = ['adyen_psp_reference'=>'ABCD1234GHJK5678',
                'adyen_notification_event_code' => 'AUTHORISATION : TRUE'];
                return $array[$key];
            }));

        $order->expects($this->any())
            ->method('getPayment')
            ->willReturn($payment);

        $order->method('getState')->willReturn(Order::STATE_NEW);

        $webhookHandler = $this->createWebhookHelperClass(
            null,
            null,
            null,
            null,
            null,
            null,
            $mockWebhookHandlerFactory,
            $orderHelper
        );

        $result = $webhookHandler->processNotification($notification);

        $this->assertTrue($result);
    }

    public function testProcessNotificationWithAdyenWebhookException()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn('TestMerchant');
        $notification->method('getEventCode')->willReturn('AUTHORISATION : FALSE');
        $notification->method('getEntityId')->willReturn('1234');
        $notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $notification->method('getPaymentMethod')->willReturn('ADYEN_CC');

        $payment = $this->createMock(Payment::class);
        $order = $this->createMock(Order::class);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getIncrementId')->willReturn(123);
        $order->method('getId')->willReturn(123);
        $order->method('getStatus')->willReturn('processing');
        $order->method('getPayment')->willReturn($payment);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('payment_method', $notification->getPaymentMethod());
        $payment->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);

        $webhookHandlerInterfaceMock = $this->createMock(WebhookHandlerInterface::class);
        $webhookHandlerInterfaceMock->method('handleWebhook')->willThrowException(new AdyenWebhookException(
            new \Magento\Framework\Phrase("Test Adyen webhook exception"
            )));

        $webhookHandlerFactory = $this->createMock(WebhookHandlerFactory::class);
        $webhookHandlerFactory->method('create')
            ->willReturn($webhookHandlerInterfaceMock);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->method('getOrderContext')->with($order);

        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);

        $webbookHelper = $this->createWebhookHelperClass(
            null,
            null,
            null,
            null,
            null,
            $logger,
            $webhookHandlerFactory,
            $orderHelper,
            null,
            $paymentMethodsHelperMock
        );

        $this->assertFalse($webbookHelper->processNotification($notification));
    }

    public function testProcessNotificationWithGeneralException()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn('TestMerchant');
        $notification->method('getEventCode')->willReturn('AUTHORISATION : FALSE');
        $notification->method('getEntityId')->willReturn('1234');
        $notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $notification->method('getPaymentMethod')->willReturn('ADYEN_CC');

        $paymentMethodInstaceMock = $this->createMock(MethodInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstaceMock);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethodInstance')->willReturn($paymentMethodInstaceMock);

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

        $webhookHandlerInterfaceMock = $this->createMock(WebhookHandlerInterface::class);
        $webhookHandlerInterfaceMock->method('handleWebhook')->willThrowException(new \Exception(
            new \Magento\Framework\Phrase("Test generic exception"
            )));

        $webhookHandlerFactory = $this->createMock(WebhookHandlerFactory::class);
        $webhookHandlerFactory->method('create')
            ->willReturn($webhookHandlerInterfaceMock);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->method('getOrderContext')->with($order);

        $paymentMethodHelperMock = $this->createMock(PaymentMethods::class);

        $webbookHelper = $this->createWebhookHelperClass(
            null,
            null,
            null,
            null,
            null,
            $logger,
            $webhookHandlerFactory,
            $orderHelper,
            null,
            $paymentMethodHelperMock
        );

        $this->assertFalse($webbookHelper->processNotification($notification));
    }

    public function testAddNotificationDetailsHistoryCommentWithFullRefund()
    {
        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);

        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAmountCurrencyObject = $this->createConfiguredMock(AdyenAmountCurrency::class, [
            'getAmount' => 100,
            'getCurrencyCode' => 'EUR'
        ]);

        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $chargedCurrencyMock->method('getOrderAmountCurrency')
            ->willReturn($orderAmountCurrencyObject);

        $adyenHelperMock = $this->createMock(Data::class);
        $adyenHelperMock->method('formatAmount')
            ->willReturn(100);

        $webhook = $this->createWebhookHelperClass(
            $adyenHelperMock,
            null,
            null,
            null,
            $chargedCurrencyMock
        );

        $notificationMock->method('getEventCode')
            ->willReturn(Notification::REFUND);
        $notificationMock->method('getAmountValue')
            ->willReturn(100);
        $notificationMock->method('isSuccessful')
            ->willReturn(true);

        $orderMock->expects($this->once())
            ->method('setData')
            ->with(
                'adyen_notification_event_code',
                $this->stringContains('REFUND : TRUE')
            );

        $reflection = new \ReflectionClass(get_class($webhook));
        $method = $reflection->getMethod('addNotificationDetailsHistoryComment');
        $method->setAccessible(true);

        $result = $method->invokeArgs($webhook, [$orderMock, $notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    public function testAddNotificationDetailsHistoryCommentWithPendingEventCode()
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configHelperMock = $this->createMock(ConfigHelper::class);
        $adyenHelperMock = $this->createMock(Data::class);
        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);

        $paymentMethodInstaceMock = $this->createMock(MethodInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstaceMock);

        $webhook = $this->createWebhookHelperClass(
            $adyenHelperMock,
            null,
            null,
            $configHelperMock,
            $chargedCurrencyMock
        );

        $notificationMock->method('getEventCode')
            ->willReturn(Notification::PENDING);
        $notificationMock->method('getPspreference')
            ->willReturn('some_psp_reference');
        $orderMock->method('getStoreId')
            ->willReturn(1);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $configHelperMock->method('getConfigData')
            ->with('pending_status', 'adyen_abstract', 1)
            ->willReturn('pending_status_value');

        $reflection = new \ReflectionClass(get_class($webhook));
        $method = $reflection->getMethod('addNotificationDetailsHistoryComment');
        $method->setAccessible(true);

        $orderMock->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with(
                $this->anything(),
                'pending_status_value'
            );

        $result = $method->invokeArgs($webhook, [$orderMock, $notificationMock]);

        $this->assertInstanceOf(Order::class, $result);
    }

    public function testUpdateOrderPaymentWithAdyenAttributes()
    {
        $paymentMock = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $webhook = $this->createWebhookHelperClass();

        $additionalData = [
            'avsResult' => 'avs_result_value',
            'cvcResult' => 'cvc_result_value',
        ];

        $notificationMock->method('getPspreference')->willReturn('pspReference');
        $notificationMock->method('getReason')->willReturn('card summary reason');

        $reflection = new \ReflectionClass(get_class($webhook));
        $method = $reflection->getMethod('updateOrderPaymentWithAdyenAttributes');
        $method->setAccessible(true);

        $paymentMock->expects($this->exactly(4))
            ->method('setAdditionalInformation')
            ->willReturnMap([
                ['adyen_avs_result', 'avs_result_value', $paymentMock],
                ['adyen_cvc_result', 'cvc_result_value', $paymentMock],
                ['pspReference', 'pspReference', $paymentMock],
                ['adyen_ratepay_descriptor', $this->anything(), $paymentMock],
            ]);

        $method->invokeArgs($webhook, [$paymentMock, $notificationMock, $additionalData]);
    }


    protected function createWebhookHelperClass(
        $mockAdyenHelper = null,
        $mockSerializer = null,
        $mockTimezone = null,
        $mockConfigHelper = null,
        $mockChargedCurrency = null,
        $mockLogger = null,
        $mockWebhookHandlerFactory = null,
        $mockOrderHelper = null,
        $mockOrderRepository = null,
        $paymentMethodsHelperMock = null,
        $adyenNotificationRepositoryMock = null
    ): Webhook {
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
        if (is_null($paymentMethodsHelperMock)) {
            $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        }
        if (is_null($adyenNotificationRepositoryMock)) {
            $adyenNotificationRepositoryMock = $this->createMock(AdyenNotificationRepositoryInterface::class);
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
            $mockOrderRepository,
            $paymentMethodsHelperMock,
            $adyenNotificationRepositoryMock
        );
    }
}
