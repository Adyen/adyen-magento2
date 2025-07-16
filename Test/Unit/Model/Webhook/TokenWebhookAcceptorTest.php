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
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class TokenWebhookAcceptorTest extends AbstractAdyenTestCase
{
    protected ?TokenWebhookAcceptor $acceptor;
    protected NotificationFactory|MockObject $notificationFactoryMock;
    protected SerializerInterface|MockObject $serializerMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected Webhook|MockObject $webhookHelperMock;
    protected NotificationReceiver|MockObject $notificationReceiverMock;
    protected Config|MockObject $configHelperMock;
    protected PaymentRepository|MockObject $paymentRepositoryMock;
    protected Http|MockObject $httpRequestMock;

    // Mock HMAC SHA256 Key
    const HMAC_KEY = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
    // Signature calculated based on the above key and the sample payload
    const HMAC_SIGNATURE = 'zwKm8q8XyIbiWjlIzV9+3eLTUhhOscxytenALWFM7IU=';

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->notificationFactoryMock = $this->createMock(NotificationFactory::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->webhookHelperMock = $this->createMock(Webhook::class);
        $this->notificationReceiverMock = $this->createPartialMock(NotificationReceiver::class, []);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->paymentRepositoryMock = $this->createMock(PaymentRepository::class);
        $this->httpRequestMock = $this->createMock(Http::class);

        $this->serializerMock->method('serialize')->willReturn(json_encode(['shopperReference' => '001']));
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn(self::HMAC_KEY);
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

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->acceptor = null;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetNotificationsReturnsNotification(): void
    {
        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);
        $this->configHelperMock->method('isDemoMode')->willReturn(true);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, reset($result));
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testValidateThrowsExceptionIfFieldMissing(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();
        // Remove an expected item from the payload
        unset($payload['data']['storedPaymentMethodId']);

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testValidateThrowsExceptionWithInvalidNotificationMode(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        // Create a confliction with the webhook payload
        $this->configHelperMock->method('isDemoMode')->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testValidateReturnsFalseIfMerchantAccountInvalid(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->configHelperMock->method('isDemoMode')->willReturn(true);

        // Invalidate merchant account
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testValidateThrowsAuthenticationExceptionWithInvalidHmacSignature(): void
    {
        $this->expectException(AuthenticationException::class);

        $payload = $this->getValidPayload();
        // Alter payload to create invalid HMAC signature
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
     * @return void
     * @throws Exception
     */
    public function testToNotificationThrowsExceptionOnDuplicate(): void
    {
        $this->expectException(AlreadyExistsException::class);

        $payload = $this->getValidPayload();

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(true);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);
        $this->configHelperMock->method('isDemoMode')->willReturn(true);

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
