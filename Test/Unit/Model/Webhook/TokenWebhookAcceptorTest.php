<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Model\Sales\Order\Payment\PaymentRepository;
use Adyen\Payment\Model\Webhook\TokenWebhookAcceptor;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class TokenWebhookAcceptorTest extends AbstractAdyenTestCase
{
    protected ?TokenWebhookAcceptor $acceptor = null;

    /** @var NotificationFactory|MockObject */
    protected $notificationFactoryMock;

    /** @var SerializerInterface|MockObject */
    protected $serializerMock;

    /** @var AdyenLogger|MockObject */
    protected $adyenLoggerMock;

    /** @var NotificationReceiver|MockObject */
    protected $notificationReceiverMock;

    /** @var Config|MockObject */
    protected $configHelperMock;

    /** @var PaymentRepository|MockObject */
    protected $paymentRepositoryMock;

    /** @var Http|MockObject */
    protected $httpRequestMock;

    // Mock HMAC SHA256 Key (hex) & signature for the base payload below
    private const HMAC_KEY = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
    private const HMAC_SIGNATURE = 'zwKm8q8XyIbiWjlIzV9+3eLTUhhOscxytenALWFM7IU=';

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->notificationFactoryMock = $this->createMock(NotificationFactory::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->notificationReceiverMock = $this->createMock(NotificationReceiver::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->paymentRepositoryMock = $this->createMock(PaymentRepository::class);
        $this->httpRequestMock = $this->createMock(Http::class);

        $this->serializerMock->method('serialize')
            ->willReturn(json_encode(['shopperReference' => '001']));

        $this->configHelperMock->method('getNotificationsHmacKey')
            ->willReturn(self::HMAC_KEY);

        $this->httpRequestMock->method('getHeader')
            ->with('hmacsignature')
            ->willReturn(self::HMAC_SIGNATURE);

        $this->acceptor = new TokenWebhookAcceptor(
            $this->notificationFactoryMock,
            $this->adyenLoggerMock,
            $this->configHelperMock,
            $this->notificationReceiverMock,
            $this->paymentRepositoryMock,
            $this->serializerMock,
            $this->httpRequestMock
        );
    }

    protected function tearDown(): void
    {
        $this->acceptor = null;
    }

    public function testGetNotificationsReturnsNotification(): void
    {
        $payload = $this->getValidPayload();

        // No order => storeId null
        $this->paymentRepositoryMock->method('getPaymentByCcTransId')->willReturn(null);

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->configHelperMock->method('isDemoMode')->willReturn(true);
        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, reset($result));
    }

    public function testUsesStoreScopeWhenOrderFound(): void
    {
        $payload = $this->getValidPayload();

        // Build payment -> order -> storeId chain
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'getIncrementId'])
            ->getMock();
        $orderMock->method('getStoreId')->willReturn(10);
        $orderMock->method('getIncrementId')->willReturn('100000001');

        $paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->paymentRepositoryMock->method('getPaymentByCcTransId')->with('evt-123')->willReturn($paymentMock);

        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // Expect isDemoMode to be called with storeId 10
        $this->configHelperMock->expects($this->once())
            ->method('isDemoMode')
            ->with(10)
            ->willReturn(true);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, reset($result));
    }

    public function testValidateThrowsExceptionIfFieldMissing(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();
        unset($payload['data']['storedPaymentMethodId']);

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // env = 'test' -> isLive 'false'
        $this->configHelperMock->method('isDemoMode')->willReturn(true);

        $this->acceptor->getNotifications($payload);
    }

    public function testValidateThrowsExceptionWithInvalidNotificationMode(): void
    {
        $this->expectException(LocalizedException::class);

        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->configHelperMock->method('isDemoMode')->willReturn(false);

        $this->notificationReceiverMock
            ->method('validateNotificationMode')
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    public function testValidateThrowsAuthenticationExceptionWithInvalidHmacSignature(): void
    {
        $this->expectException(AuthenticationException::class);

        $payload = $this->getValidPayload();
        // Mutate payload so the precomputed signature no longer matches
        $payload['data']['storedPaymentMethodId'] = 'alternative_psp';

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->notificationReceiverMock
            ->method('validateNotificationMode')
            ->willReturn(true);

        $this->configHelperMock->method('isDemoMode')->willReturn(true);
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn('mock_hmac_key');

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    public function testLogsAndContinuesWhenPaymentLoadThrows(): void
    {
        $payload = $this->getValidPayload();
        $eventId = $payload['eventId'];

        // Force the catch block
        $this->paymentRepositoryMock->expects($this->once())
            ->method('getPaymentByCcTransId')
            ->with($eventId)
            ->willThrowException(new \RuntimeException('DB down'));

        // Expect a log line with the formatted message and full payload
        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->callback(function (string $msg) use ($eventId) {
                    return str_contains($msg, "Could not load payment for reference $eventId: DB down");
                })
            );

        $this->notificationReceiverMock
            ->method('validateNotificationMode')
            ->willReturn(true);

        // Since payment/order resolution failed, storeId must be null
        $this->configHelperMock->expects($this->once())
            ->method('isDemoMode')
            ->with(null)
            ->willReturn(true);

        // HMAC path: use the pre-configured valid signature from setUp()
        // Notification creation (not duplicate) to allow completion
        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, reset($result));
    }

    private function getValidPayload(): array
    {
        return [
            'eventId' => 'evt-123',
            'type' => 'token.created',
            'data' => [
                'storedPaymentMethodId' => 'mock-psp',
                'type' => 'visa',
                'shopperReference' => 'shopper123',
                'merchantAccount' => 'TestMerchant',
            ],
            'environment' => 'test'
        ];
    }
}
