<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Exception\AuthenticationException;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Model\Webhook\TokenWebhookAcceptor;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TokenWebhookAcceptorTest extends TestCase
{
    private TokenWebhookAcceptor $acceptor;
    private MockObject $notificationFactoryMock;
    private MockObject $serializerMock;
    private MockObject $adyenLoggerMock;
    private MockObject $webhookHelperMock;

    protected function setUp(): void
    {
        $this->notificationFactoryMock = $this->createMock(NotificationFactory::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->webhookHelperMock = $this->createMock(Webhook::class);

        $this->acceptor = new TokenWebhookAcceptor(
            $this->notificationFactoryMock,
            $this->serializerMock,
            $this->adyenLoggerMock,
            $this->webhookHelperMock
        );
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

    public function testValidateReturnsFalseIfIpInvalid(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(false);
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $result = $this->acceptor->validate($this->getValidPayload());
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseIfFieldMissing(): void
    {
        $payload = $this->getValidPayload();
        unset($payload['data']['storedPaymentMethodId']);

        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $result = $this->acceptor->validate($payload);
        $this->assertFalse($result);
    }

    public function testValidateReturnsFalseIfMerchantAccountInvalid(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(false);

        $payload = $this->getValidPayload();

        $result = $this->acceptor->validate($payload);
        $this->assertFalse($result);
    }

    public function testValidateReturnsTrueOnValidPayload(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);

        $result = $this->acceptor->validate($this->getValidPayload());
        $this->assertTrue($result);
    }

    public function testToNotificationListThrowsAuthenticationException(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token webhook failed authentication or validation.');

        $this->acceptor->toNotificationList($this->getValidPayload());
    }

    public function testToNotificationListReturnsNotification(): void
    {
        $payload = $this->getValidPayload();

        $notification = $this->getMockBuilder(Notification::class)
            ->onlyMethods(['setPspreference', 'setOriginalReference', 'setMerchantReference',
                'setEventCode', 'setPaymentMethod', 'setLive', 'setSuccess', 'setReason',
                'setAdditionalData', 'setCreatedAt', 'setUpdatedAt', 'isDuplicate'])
            ->disableOriginalConstructor()
            ->getMock();

        $notification->method('isDuplicate')->willReturn(false);

        $this->notificationFactoryMock->method('create')->willReturn($notification);
        $this->serializerMock->method('serialize')->willReturn(json_encode($payload));

        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);

        $result = $this->acceptor->toNotificationList($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, $result[0]);
    }

    public function testToNotificationThrowsOnDuplicate(): void
    {
        $payload = $this->getValidPayload();

        $notification = $this->getMockBuilder(Notification::class)
            ->onlyMethods(['isDuplicate'])
            ->disableOriginalConstructor()
            ->getMock();

        $notification->method('isDuplicate')->willReturn(true);
        $this->notificationFactoryMock->method('create')->willReturn($notification);
        $this->serializerMock->method('serialize')->willReturn(json_encode($payload));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate token notification');

        $this->acceptor->toNotification($payload, 'test');
    }
}
