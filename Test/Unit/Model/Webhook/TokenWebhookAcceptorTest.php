<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook;
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
use Magento\Framework\Exception\AlreadyExistsException;
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

    /** @var Webhook|MockObject */
    protected $webhookHelperMock;

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
        $this->serializerMock         = $this->createMock(SerializerInterface::class);
        $this->adyenLoggerMock        = $this->createMock(AdyenLogger::class);
        $this->webhookHelperMock      = $this->createMock(Webhook::class);
        // Use partial mock with original methods (no constructor)
        $this->notificationReceiverMock = $this->createPartialMock(NotificationReceiver::class, []);
        $this->configHelperMock         = $this->createMock(Config::class);
        $this->paymentRepositoryMock    = $this->createMock(PaymentRepository::class);
        $this->httpRequestMock          = $this->createMock(Http::class);

        $this->serializerMock->method('serialize')
            ->willReturn(json_encode(['shopperReference' => '001']));

        // Default: enable HMAC and provide matching signature for the base payload
        $this->configHelperMock->method('getNotificationsHmacKey')
            ->willReturn(self::HMAC_KEY);

        $this->httpRequestMock->method('getHeader')
            ->with('hmacsignature')
            ->willReturn(self::HMAC_SIGNATURE);

        $this->acceptor = new TokenWebhookAcceptor(
            $this->notificationFactoryMock,
            $this->adyenLoggerMock,
            $this->webhookHelperMock,
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

    /**
     * Happy path: returns 1 notification.
     * Uses null store scope (no order found) and passes validation.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
    public function testGetNotificationsReturnsNotification(): void
    {
        $payload = $this->getValidPayload();

        // No order => storeId null
        $this->paymentRepositoryMock->method('getPaymentByCcTransId')->willReturn(null);

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // env = 'test' -> isLive 'false' => demo must be true
        $this->configHelperMock->method('isDemoMode')->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, reset($result));
    }

    /**
     * Ensures store scope (storeId) from order is used when available.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
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

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // Expect isDemoMode to be called with storeId 10
        $this->configHelperMock->expects($this->once())
            ->method('isDemoMode')
            ->with(10)
            ->willReturn(true);

        // Expect merchant account validation to be called with storeId 10
        $this->webhookHelperMock->expects($this->once())
            ->method('isMerchantAccountValid')
            ->with('TestMerchant', $payload, 10)
            ->willReturn(true);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, reset($result));
    }

    /**
     * Missing a required nested field => InvalidDataException.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
    public function testValidateThrowsExceptionIfFieldMissing(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();
        unset($payload['data']['storedPaymentMethodId']);

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // env = 'test' -> isLive 'false' => demo must be true to reach field validation branch
        $this->configHelperMock->method('isDemoMode')->willReturn(true);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * Early guard: eventId missing => InvalidDataException.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
    public function testGetNotificationsThrowsInvalidDataExceptionIfEventIdMissing(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();
        unset($payload['eventId']);

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * Invalid notification mode for env/test vs demo flag => InvalidDataException.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
    public function testValidateThrowsExceptionWithInvalidNotificationMode(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // env 'test' -> isLive 'false', set demo=false to force invalid mode
        $this->configHelperMock->method('isDemoMode')->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * Merchant account invalid => InvalidDataException.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
    public function testValidateThrowsExceptionIfMerchantAccountInvalid(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // Pass mode validation
        $this->configHelperMock->method('isDemoMode')->willReturn(true);

        // Fail merchant account validation
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * HMAC mismatch => AuthenticationException.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
    public function testValidateThrowsAuthenticationExceptionWithInvalidHmacSignature(): void
    {
        $this->expectException(AuthenticationException::class);

        $payload = $this->getValidPayload();
        // Mutate payload so the precomputed signature no longer matches
        $payload['data']['storedPaymentMethodId'] = 'alternative_psp';

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->configHelperMock->method('isDemoMode')->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * Duplicate notification => AlreadyExistsException.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws InvalidDataException
     */
    public function testToNotificationThrowsExceptionOnDuplicate(): void
    {
        $this->expectException(AlreadyExistsException::class);

        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(true);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->configHelperMock->method('isDemoMode')->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);

        $this->acceptor->getNotifications($payload);
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
