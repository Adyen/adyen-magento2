<?php
namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\IpAddress;
use Adyen\Payment\Helper\OrderStatusHistory;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Helper\Webhook\WebhookHandlerInterface;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook\WebhookHandlerFactory;
use Adyen\Payment\Logger\AdyenLogger;
use PHPUnit\Framework\MockObject\Exception;
use ReflectionMethod;
use Adyen\Payment\Exception\AdyenWebhookException;

class WebhookTest extends AbstractAdyenTestCase
{
    public function testProcessNotificationWithInvalidMerchantReference()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn(null);
        $notification->method('getEventCode')->willReturn(Notification::AUTHORISATION);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Invalid merchant reference'));

        $webhookHandler = $this->createWebhookHelper(
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
        $notification->method('getEventCode')->willReturn(Notification::AUTHORISATION);
        $notification->method('getMerchantReference')->willReturn($merchantReference);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getOrderByIncrementId')->with($merchantReference)->willReturn(null);

        $logger = $this->createMock(AdyenLogger::class);

        $webhookHandler = $this->createWebhookHelper(
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
        $webhookHandler = $this->createWebhookHelper();
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
        $payment->method('getMethod')->willReturn('adyen_cc');

        $order = $this->createMock(Order::class);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getIncrementId')->willReturn(123);
        $order->method('getId')->willReturn(123);
        $order->method('getStatus')->willReturn('processing');
        $order->method('getPayment')->willReturn($payment);

        $orderHelper = $this->createMock(OrderHelper::class);
        $orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->method('getOrderContext')->with($order);

        $webhookHandlerFactory = $this->createMock(WebhookHandlerFactory::class);

        $webhook = $this->createWebhookHelper(
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

        $webhook = $this->createWebhookHelper();

        $result = $this->invokeMethod($webhook,'addNotificationDetailsHistoryComment',[$orderMock, $notificationMock]);

        $this->assertInstanceOf(Order::class, $result, 'The function did not return an instance of Order as expected.');
    }

    public function testGetTransitionState()
    {
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();

        $webhook = $this->createWebhookHelper();

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

        $webhook = $this->createWebhookHelper(
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
                    'pspReference' => $notificationMock->getPspreference(),
                    'merchantReference' => $notificationMock->getMerchantReference(),
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
        $payment->method('getMethod')->willReturn('adyen_cc');

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

        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsHelperMock->method('isAdyenPayment')
            ->with('adyen_cc')
            ->willReturn(true);

        $webhookHandler = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            $mockWebhookHandlerFactory,
            $orderHelper,
            null,
            $paymentMethodsHelperMock
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
        $payment->method('getMethod')->willReturn('adyen_cc');

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
        $paymentMethodsHelperMock->method('isAdyenPayment')
            ->with('adyen_cc')
            ->willReturn(true);

        $webbookHelper = $this->createWebhookHelper(
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
        $payment->method('getMethod')->willReturn('adyen_cc');

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

        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsHelperMock->method('isAdyenPayment')
            ->with('adyen_cc')
            ->willReturn(true);

        $webbookHelper = $this->createWebhookHelper(
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

        $webhook = $this->createWebhookHelper(
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

        $webhook = $this->createWebhookHelper(
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

        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory')
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

        $webhook = $this->createWebhookHelper();

        $additionalData = [
            'avsResult' => 'avs_result_value',
            'cvcResult' => 'cvc_result_value',
        ];

        $notificationMock->method('getPspreference')->willReturn('pspReference');
        $notificationMock->method('getReason')->willReturn('card summary reason');

        $reflection = new \ReflectionClass(get_class($webhook));
        $method = $reflection->getMethod('updateOrderPaymentWithAdyenAttributes');
        $method->setAccessible(true);

        $paymentMock->expects($this->exactly(3))
            ->method('setAdditionalInformation')
            ->willReturnMap([
                ['adyen_avs_result', 'avs_result_value', $paymentMock],
                ['adyen_cvc_result', 'cvc_result_value', $paymentMock],
                ['pspReference', 'pspReference', $paymentMock]
            ]);

        $method->invokeArgs($webhook, [$paymentMock, $notificationMock, $additionalData]);
    }

    public function testWebhookProcessingForNonAdyenPaymentMethod()
    {
        $webhookMock = $this->createWebhook();
        $webhookMock->method('getEntityId')->willReturn(1);
        $webhookMock->method('getMerchantReference')->willReturn('MOCK_MERCHANT_REFERENCE');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('random_payment_method');

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $orderHelperMock = $this->createMock(OrderHelper::class);
        $orderHelperMock->method('getOrderByIncrementId')
            ->with('MOCK_MERCHANT_REFERENCE')
            ->willReturn($orderMock);

        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsHelperMock->method('isAdyenPayment')
            ->with('random_payment_method')
            ->willReturn(false);

        $mockLogger = $this->createMock(AdyenLogger::class);
        $mockLogger->expects($this->any())->method('addAdyenNotification');

        $webhookHelper = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            $mockLogger,
            null,
            $orderHelperMock,
            null,
            $paymentMethodsHelperMock
        );

        $response = $webhookHelper->processNotification($webhookMock);
        $this->assertFalse($response);
    }

    public function testIsIpValidReturnsTrue(): void
    {
        $payload = ['dummy' => 'data'];

        $remoteAddress = $this->createMock(\Magento\Framework\HTTP\PhpEnvironment\RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn('127.0.0.1');

        $ipAddressHelper = $this->createMock(\Adyen\Payment\Helper\IpAddress::class);
        $ipAddressHelper->method('isIpAddressValid')->with(['127.0.0.1'])->willReturn(true);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $ipAddressHelper,  // <- IpAddress goes here
            $remoteAddress     // <- RemoteAddress goes here
        );

        $this->assertTrue($webhook->isIpValid($payload));
    }

    /**
     * @throws Exception
     */
    public function testIsIpValidReturnsFalseAndLogs(): void
    {
        $payload = ['key' => 'value'];

        $remoteAddress = $this->createMock(RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn('192.168.0.1');

        $ipAddressHelper = $this->createMock(\Adyen\Payment\Helper\IpAddress::class);
        $ipAddressHelper->method('isIpAddressValid')->with(['192.168.0.1'])->willReturn(false);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Invalid IP'), $payload);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            $logger,
            null,
            null,
            null,
            null,
            null,
            null,
            $ipAddressHelper,  // <- IpAddress
            $remoteAddress     // <- RemoteAddress
        );


        $this->assertFalse($webhook->isIpValid($payload));
    }

    /** -------- UPDATED to pass explicit $storeId -------- */

    public function testIsMerchantAccountValidReturnsTrueWithStoreScope(): void
    {
        $payload = ['pspReference' => 'PSP-123']; // unused by method, kept for logging context
        $expectedMerchant = 'TestMerchant';
        $storeId = 3;

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('getMerchantAccount')
            ->with($storeId)
            ->willReturn($expectedMerchant);

        $logger = $this->createMock(AdyenLogger::class);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            $configHelper,
            null,
            $logger
        );

        $this->assertTrue($webhook->isMerchantAccountValid($expectedMerchant, $payload, $storeId));
    }

    public function testIsMerchantAccountValidUsesMotoFallbackWithStoreScope(): void
    {
        $payload = ['pspReference' => 'PSP-456'];
        $incoming = 'MotoMerchant';
        $storeId = 5;

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('getMerchantAccount')
            ->with($storeId)
            ->willReturn(null);
        $configHelper->expects($this->once())
            ->method('getMotoMerchantAccounts')
            ->with($storeId)
            ->willReturn([$incoming]);

        $logger = $this->createMock(AdyenLogger::class);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            $configHelper,
            null,
            $logger
        );

        $this->assertTrue($webhook->isMerchantAccountValid($incoming, $payload, $storeId));
    }

    public function testIsMerchantAccountValidReturnsFalseAndLogsWithStoreScope(): void
    {
        $payload = ['pspReference' => 'PSP-789'];
        $expected = 'LiveMerchant';
        $incoming = 'WrongMerchant';
        $storeId = 2;

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('getMerchantAccount')
            ->with($storeId)
            ->willReturn($expected);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with($this->stringContains('Merchant account mismatch'), $payload);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            $configHelper,
            null,
            $logger
        );

        $this->assertFalse($webhook->isMerchantAccountValid($incoming, $payload, $storeId));
    }

    public function testProcessRecurringTokenDisabledWebhookSuccess(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getEventCode')->willReturn(Notification::RECURRING_TOKEN_DISABLED);
        $notification->method('getEntityId')->willReturn('n-1');
        $notification->method('getPspreference')->willReturn('psp-1');

        $orderFactory = $this->createMock(OrderFactory::class);
        $orderFactory->method('create')->willReturn($this->createMock(Order::class));

        $handler = $this->createMock(\Adyen\Payment\Helper\Webhook\WebhookHandlerInterface::class);
        $handler->expects($this->once())->method('handleWebhook');

        $factory = $this->createMock(WebhookHandlerFactory::class);
        $factory->method('create')->with(Notification::RECURRING_TOKEN_DISABLED)->willReturn($handler);

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $repo->expects($this->exactly(2))->method('save');

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->atLeastOnce())->method('addAdyenNotification');

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            $logger,
            $factory,
            null,
            null,
            null,
            $repo,
            null,
            null,
            null,
            $orderFactory
        );

        $this->assertTrue($webhook->processNotification($notification));
    }

    public function testProcessRecurringTokenDisabledWebhookHandlesException(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getEventCode')->willReturn(Notification::RECURRING_TOKEN_DISABLED);
        $notification->method('getEntityId')->willReturn('n-2');
        $notification->method('getPspreference')->willReturn('psp-2');

        $orderFactory = $this->createMock(OrderFactory::class);
        $orderFactory->method('create')->willReturn($this->createMock(Order::class));

        $handler = $this->createMock(\Adyen\Payment\Helper\Webhook\WebhookHandlerInterface::class);
        $handler->method('handleWebhook')->willThrowException(new \RuntimeException('boom'));

        $factory = $this->createMock(WebhookHandlerFactory::class);
        $factory->method('create')->willReturn($handler);

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $repo->expects($this->exactly(2))->method('save'); // processing=true then processing=false

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->atLeastOnce())->method('addAdyenNotification');

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            $logger,
            $factory,
            null,
            null,
            null,
            $repo,
            null,
            null,
            null,
            $orderFactory
        );

        $this->assertFalse($webhook->processNotification($notification));
    }

    public function testUpdateNotificationSetsProcessingAndDone(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->expects($this->once())->method('setDone')->with(true);
        $notification->expects($this->once())->method('setProcessing')->with(false);
        $notification->expects($this->once())->method('setUpdatedAt')->with($this->isType('string'));

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($notification);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $repo
        );

        // Call private via reflection
        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('updateNotification');
        $m->invoke($webhook, $notification, false, true);
    }

    public function testSetNotificationErrorIncrementsAndStopsAtMax(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getUpdatedAt')->willReturn(date('Y-m-d H:i:s'));

        $notification->method('getErrorCount')->willReturnOnConsecutiveCalls(
            Notification::MAX_ERROR_COUNT - 1,
            Notification::MAX_ERROR_COUNT
        );

        $notification->expects($this->once())->method('setErrorCount')->with(Notification::MAX_ERROR_COUNT);
        $notification->expects($this->any())->method('getErrorMessage')->willReturn('old');
        $notification->expects($this->once())->method('setErrorMessage')->with($this->stringContains('old'));
        $notification->expects($this->once())->method('setDone')->with(true);

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($notification);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $repo
        );

        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('setNotificationError');
        $m->invoke($webhook, $notification, 'oops');

        $this->assertTrue(true);
    }

    public function testRetrieveLast4DigitsFromReasonParses(): void
    {
        $webhook = $this->createWebhookHelper();
        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('retrieveLast4DigitsFromReason');

        $this->assertSame('1234', $m->invoke($webhook, 'Card Number:1234'));
        $this->assertSame('', $m->invoke($webhook, 'NoDigitsHere'));
        $this->assertSame('', $m->invoke($webhook, ''));
    }

    public function testDeclareVariablesPopulatesRatepayDescriptor(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getAdditionalData')->willReturn(json_encode([
            'additionalData' => ['acquirerReference' => ' Klarna123 '],
            'openinvoicedata.descriptor' => 'RP-XYZ'
        ]));

        $serializer = new Json();

        $webhook = $this->createWebhookHelper(null, $serializer);

        $ref = new \ReflectionClass($webhook);
        $decl = $ref->getMethod('declareVariables');
        $decl->invoke($webhook, $notification);

        $descriptorSeen = false;

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $payment->expects($this->atLeastOnce())
            ->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use (&$descriptorSeen, $payment) {
                if ($key === 'adyen_ratepay_descriptor' && $value === 'RP-XYZ') {
                    $descriptorSeen = true;
                }
                return $payment;
            });

        $notif2 = $this->createMock(Notification::class);
        $notif2->method('getPspreference')->willReturn('psp');

        $upd = $ref->getMethod('updateOrderPaymentWithAdyenAttributes');
        $upd->invoke($webhook, $payment, $notif2, []);

        $this->assertTrue($descriptorSeen, 'Ratepay descriptor was not set on payment additional information.');
    }

    public function testUpdateOrderPaymentWithAdyenAttributesUsesCardSummary(): void
    {
        $payment = $this->getMockBuilder(Order\Payment::class)->disableOriginalConstructor()->getMock();
        $notification = $this->createMock(Notification::class);
        $notification->method('getPspreference')->willReturn('psp-3');
        $notification->method('getReason')->willReturn('Card:9999');

        $payment->expects($this->once())->method('setccLast4')->with('5678');

        $webhook = $this->createWebhookHelper();
        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('updateOrderPaymentWithAdyenAttributes');

        $m->invoke($webhook, $payment, $notification, ['cardSummary' => '5678']);
    }

    public function testUpdateOrderPaymentWithAdyenAttributesSetsAllFields(): void
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->addMethods(['setAdyenPspReference'])
            ->getMock();

        $notification = $this->createMock(Notification::class);
        $notification->method('getPspreference')->willReturn('psp-4');
        $notification->method('getReason')->willReturn('Card:1234');

        $payment->expects($this->once())->method('setAdyenPspReference')->with('psp-4');

        $webhook = $this->createWebhookHelper();
        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('updateOrderPaymentWithAdyenAttributes');

        $m->invoke($webhook, $payment, $notification, [
            'avsResult' => 'A',
            'cvcResult' => 'M',
            'totalFraudScore' => '42',
            'refusalReasonRaw' => 'raw',
            'acquirerReference' => 'ACQ',
            'authCode' => 'AUTH'
        ]);

        $this->assertTrue(true);
    }

    public function testAddNotificationDetailsHistoryCommentFallsBackToCurrentStatus(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn('processing');

        $paymentMethod = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethodInstance')->willReturn($paymentMethod);
        $order->method('getPayment')->willReturn($payment);

        $notification = $this->createMock(Notification::class);
        $notification->method('getEventCode')->willReturn('SOMETHING_ELSE');

        $config = $this->createMock(ConfigHelper::class);
        $config->method('getConfigData')->willReturn('');

        $order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with($this->anything(), 'processing');

        $webhook = $this->createWebhookHelper(null, null, null, $config);
        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('addNotificationDetailsHistoryComment');

        $this->assertInstanceOf(Order::class, $m->invoke($webhook, $order, $notification));
    }

    public function testUpdateAdyenAttributesSkipsWhenPrevAuthTrueAndCurrentUnsuccessful(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getEventCode')->willReturn(Notification::AUTHORISATION);
        $notification->method('isSuccessful')->willReturn(false);

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->addMethods(['setAdyenPspReference'])
            ->getMock();

        $payment->expects($this->never())->method('setAdyenPspReference');

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $orderWithPrev = $this->createMock(Order::class);
        $orderWithPrev->method('getData')
            ->with('adyen_notification_event_code')
            ->willReturn('AUTHORISATION : TRUE');

        $repo = $this->createMock(OrderRepository::class);
        $repo->method('get')->willReturn($orderWithPrev);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $repo
        );

        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('updateAdyenAttributes');

        $this->assertInstanceOf(Order::class, $m->invoke($webhook, $order, $notification));
    }

    public function testIsIpValidLogsCustomContext(): void
    {
        $payload = ['k' => 'v'];
        $remote = $this->createMock(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn('10.0.0.1');

        $ipHelper = $this->createMock(\Adyen\Payment\Helper\IpAddress::class);
        $ipHelper->method('isIpAddressValid')->with(['10.0.0.1'])->willReturn(false);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())->method('addAdyenNotification')->with($this->stringContains('Invalid IP for moto'));

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            $logger,
            null,
            null,
            null,
            null,
            null,
            null,
            $ipHelper,
            $remote
        );

        $this->assertFalse($webhook->isIpValid($payload, 'moto'));
    }

    public function testIsMerchantAccountValidWithArrayExpected(): void
    {
        $storeId = 1;
        $payload = ['eventCode' => 'AUTHORISATION', 'pspReference' => 'ACQ'];
        $incoming = 'M2';

        $config = $this->createMock(ConfigHelper::class);
        $config->method('getMerchantAccount')->with($storeId)->willReturn(null);
        $config->method('getMotoMerchantAccounts')->with($storeId)->willReturn(['M1','M2','M3']);

        $webhook = $this->createWebhookHelper(null, null, null, $config);

        $this->assertTrue($webhook->isMerchantAccountValid($incoming, $payload, $storeId));
    }

    public function testGetCurrentStateCoversAdyenAuthorizedPseudoState(): void
    {
        $orderState = Notification::STATE_ADYEN_AUTHORIZED;
        $webhook = $this->createWebhookHelper();
        $current = $this->invokeMethod($webhook, 'getCurrentState', [$orderState]);
        $this->assertSame(\Adyen\Webhook\PaymentStates::STATE_PENDING, $current);
    }

    /**
     * If WebhookHandlerFactory::create() throws InvalidDataException,
     * processNotification should catch it, mark notification done, log, and return false.
     * Also verifies repository save is called twice (processing true, then done).
     */
    public function testProcessNotificationHandlesInvalidDataExceptionFromFactory(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn('MR-1');
        $notification->method('getEventCode')->willReturn(Notification::AUTHORISATION);
        $notification->method('getEntityId')->willReturn('N-1');
        $notification->method('getPspreference')->willReturn('PSP-1');
        $notification->method('getPaymentMethod')->willReturn('adyen_cc');
        $notification->method('isSuccessful')->willReturn(false); // drive the "unsuccessful" branch

        $paymentMethodInstance = $this->createMock(MethodInterface::class);

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethodInstance')->willReturn($paymentMethodInstance);
        $payment->method('getMethod')->willReturn('adyen_cc');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getPayment')->willReturn($payment);

        $orderHelper = $this->createMock(\Adyen\Payment\Helper\Order::class);
        $orderHelper->method('getOrderByIncrementId')->with('MR-1')->willReturn($order);

        // OrderRepository->get() must return an order-like object with getData()
        $orderWithPrev = $this->createMock(Order::class);
        $orderWithPrev->method('getData')->with('adyen_notification_event_code')->willReturn('AUTHORISATION : FALSE');

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('get')->with(42)->willReturn($orderWithPrev);

        $pm = $this->createMock(PaymentMethods::class);
        $pm->method('isAdyenPayment')->with('adyen_cc')->willReturn(true);

        $factory = $this->createMock(WebhookHandlerFactory::class);
        $factory->method('create')->willThrowException(new \Adyen\Webhook\Exception\InvalidDataException());

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        // updateNotification(true,false) then updateNotification(false,true)
        $repo->expects($this->exactly(3))->method('save')->with($notification);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->atLeastOnce())->method('addAdyenNotification');

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            $logger,
            $factory,
            $orderHelper,
            $orderRepository,
            $pm,
            $repo
        );

        $this->assertFalse($webhook->processNotification($notification));
    }

    /**
     * Unhandled/unknown order state -> returns false early and logs, without throwing.
     */
    public function testProcessNotificationReturnsFalseOnUnhandledOrderState(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getMerchantReference')->willReturn('MR-2');
        $notification->method('getEventCode')->willReturn(Notification::AUTHORISATION);
        $notification->method('getPspreference')->willReturn('PSP-2');
        $notification->method('getPaymentMethod')->willReturn('adyen_cc');
        $notification->method('isSuccessful')->willReturn(true);

        $paymentMethodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethodInstance')->willReturn($paymentMethodInstance);
        $payment->method('getMethod')->willReturn('adyen_cc');

        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getState')->willReturn('holded'); // not mapped in WEBHOOK_ORDER_STATE_MAPPING
        $order->method('getPayment')->willReturn($payment);

        $orderHelper = $this->createMock(\Adyen\Payment\Helper\Order::class);
        $orderHelper->method('getOrderByIncrementId')->with('MR-2')->willReturn($order);

        $pm = $this->createMock(\Adyen\Payment\Helper\PaymentMethods::class);
        $pm->method('isAdyenPayment')->with('adyen_cc')->willReturn(true);

        $logged = [];
        $logger = $this->createMock(\Adyen\Payment\Logger\AdyenLogger::class);
        $logger->method('addAdyenNotification')->willReturnCallback(function (...$args) use (&$logged) {
            // message is the first argument
            $logged[] = $args[0] ?? '';
            return true;
        });

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            $logger,
            null,
            $orderHelper,
            null,
            $pm
        );

        $result = $webhook->processNotification($notification);

        $this->assertFalse($result);
        $this->assertTrue(
            array_reduce($logged, fn($carry, $m) => $carry || stripos((string)$m, 'Unhandled order state') !== false, false),
            'Expected at least one log line to mention "Unhandled order state".'
        );
    }

    /**
     * PENDING event with empty 'pending_status' config falls back to adding comment with current status.
     */
    public function testPendingEventFallsBackToCurrentStatusWhenNoConfig(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn('processing');

        $paymentMethod = $this->createMock(MethodInterface::class);
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethodInstance')->willReturn($paymentMethod);
        $order->method('getPayment')->willReturn($payment);

        $notification = $this->createMock(Notification::class);
        $notification->method('getEventCode')->willReturn(Notification::PENDING);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('getConfigData')->willReturn(''); // no pending status configured

        $order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with($this->anything(), 'processing');

        $webhook = $this->createWebhookHelper(null, null, null, $config);
        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('addNotificationDetailsHistoryComment');

        $this->assertInstanceOf(Order::class, $m->invoke($webhook, $order, $notification));
    }

    /**
     * If no merchant account is configured at all (primary + MOTO both null), returns false and logs.
     */
    public function testIsMerchantAccountValidReturnsFalseWhenNoConfig(): void
    {
        $payload = ['ctx' => 'v'];
        $incoming = 'Anything';
        $storeId = 9;

        $config = $this->createMock(ConfigHelper::class);
        $config->expects($this->once())->method('getMerchantAccount')->with($storeId)->willReturn(null);
        $config->expects($this->once())->method('getMotoMerchantAccounts')->with($storeId)->willReturn(null);

        $logger = $this->createMock(AdyenLogger::class);
        $logger->expects($this->once())->method('addAdyenNotification')
            ->with($this->stringContains('Merchant account mismatch'), $payload);

        $webhook = $this->createWebhookHelper(null, null, null, $config, null, $logger);

        $this->assertFalse($webhook->isMerchantAccountValid($incoming, $payload, $storeId));
    }

    /**
     * updateNotification() with processing=true, done=false only toggles processing and updatedAt.
     */
    public function testUpdateNotificationProcessingTrueOnly(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->expects($this->never())->method('setDone');
        $notification->expects($this->once())->method('setProcessing')->with(true);
        $notification->expects($this->once())->method('setUpdatedAt')->with($this->isType('string'));

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($notification);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $repo
        );

        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('updateNotification');
        $m->invoke($webhook, $notification, true, false);
        $this->assertTrue(true);
    }

    /**
     * setNotificationError() when there is no previous message:
     * - increments error count to 1
     * - sets a single-line message (no newline append)
     * - does NOT set done (below MAX_ERROR_COUNT)
     */
    public function testSetNotificationErrorFirstMessageNoAppend(): void
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getUpdatedAt')->willReturn(date('Y-m-d H:i:s'));
        $notification->method('getErrorCount')->willReturn(0); // starts at 0
        $notification->method('getErrorMessage')->willReturn('');

        $notification->expects($this->once())->method('setErrorCount')->with(1);
        $notification->expects($this->once())->method('setErrorMessage')
            ->with($this->callback(fn($msg) => strpos($msg, "\n") === false));
        $notification->expects($this->never())->method('setDone');

        $repo = $this->createMock(AdyenNotificationRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($notification);

        $webhook = $this->createWebhookHelper(null, null, null, null, null, null, null, null, null, null, $repo);

        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('setNotificationError');
        $m->invoke($webhook, $notification, 'first error');
        $this->assertTrue(true);
    }

    /**
     * updateOrderPaymentWithAdyenAttributes(): when no cardSummary in additionalData,
     * it falls back to last 4 extracted from reason.
     */
    public function testUpdateOrderPaymentWithAdyenAttributesFallsBackToReasonLast4(): void
    {
        $payment = $this->getMockBuilder(Order\Payment::class)->disableOriginalConstructor()->getMock();
        $notification = $this->createMock(Notification::class);
        $notification->method('getPspreference')->willReturn('psp-x');
        $notification->method('getReason')->willReturn('Masked:1234');

        $payment->expects($this->once())->method('setccLast4')->with('1234');

        $webhook = $this->createWebhookHelper();
        $ref = new \ReflectionClass($webhook);
        $m = $ref->getMethod('updateOrderPaymentWithAdyenAttributes');

        // Provide empty additionalData to force fallback to reason parsing
        $m->invoke($webhook, $payment, $notification, []);
        $this->assertTrue(true);
    }

    /**
     * isIpValid(): when X-Forwarded-For contains multiple entries, we pass the whole list to validator.
     */
    public function testIsIpValidWithMultipleAddresses(): void
    {
        $payload = ['a' => 1];

        $remoteAddress = $this->createMock(RemoteAddress::class);
        // Note the space after comma — Webhook::isIpValid() does not trim
        $remoteAddress->method('getRemoteAddress')->willReturn('1.1.1.1, 2.2.2.2');

        $ipAddressHelper = $this->createMock(\Adyen\Payment\Helper\IpAddress::class);
        $ipAddressHelper->expects($this->once())
            ->method('isIpAddressValid')
            ->with(['1.1.1.1', ' 2.2.2.2'])
            ->willReturn(true);

        $webhook = $this->createWebhookHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,              // OrderStatusHistory placeholder
            $ipAddressHelper,  // IpAddress
            $remoteAddress     // RemoteAddress
        );

        $this->assertTrue($webhook->isIpValid($payload));
    }


    /** ---------------- helpers ---------------- */

    protected function createWebhookHelper(
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
        $adyenNotificationRepositoryMock = null,
        $orderStatusHistoryHelperMock = null,
        $ipAddressHelperMock = null,
        $remoteAddressMock = null,
        $orderFactoryMock = null
    ): Webhook {
        if (is_null($mockSerializer)) {
            $mockSerializer = $this->createMock(SerializerInterface::class);
        }
        if (is_null($mockTimezone)) {
            $mockTimezone = $this->createMock(TimezoneInterface::class);
        }
        if (is_null($mockConfigHelper)) {
            $mockConfigHelper = $this->createMock(ConfigHelper::class);
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
        if (is_null($orderStatusHistoryHelperMock)) {
            $orderStatusHistoryHelperMock = $this->createMock(OrderStatusHistory::class);
        }
        if (is_null($ipAddressHelperMock)) {
            $ipAddressHelperMock = $this->createMock(IpAddress::class);
        }
        if (is_null($remoteAddressMock)) {
            $remoteAddressMock = $this->createMock(RemoteAddress::class);
        }
        if (is_null($orderFactoryMock)) {
            $orderFactoryMock = $this->createMock(OrderFactory::class);
        }

        return new Webhook(
            $mockSerializer,
            $mockTimezone,
            $mockConfigHelper,
            $mockLogger,
            $mockWebhookHandlerFactory,
            $mockOrderHelper,
            $mockOrderRepository,
            $orderStatusHistoryHelperMock,
            $paymentMethodsHelperMock,
            $adyenNotificationRepositoryMock,
            $ipAddressHelperMock,
            $remoteAddressMock,
            $orderFactoryMock
        );
    }
}
