<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Exception\AuthenticationException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Model\Webhook\StandardWebhookAcceptor;
use Adyen\Webhook\Exception\HMACKeyValidationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;

class StandardWebhookAcceptorTest extends TestCase
{
    private StandardWebhookAcceptor $acceptor;
    private Config $configMock;
    private NotificationFactory $notificationFactoryMock;
    private NotificationReceiver $notificationReceiverMock;
    private HmacSignature $hmacSignatureMock;
    private SerializerInterface $serializerMock;
    private AdyenLogger $adyenLoggerMock;
    private Webhook $webhookHelperMock;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->notificationFactoryMock = $this->createMock(NotificationFactory::class);
        $this->notificationReceiverMock = $this->createMock(NotificationReceiver::class);
        $this->hmacSignatureMock = $this->createMock(HmacSignature::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->webhookHelperMock = $this->createMock(Webhook::class);

        $this->acceptor = new StandardWebhookAcceptor(
            $this->configMock,
            $this->notificationFactoryMock,
            $this->notificationReceiverMock,
            $this->hmacSignatureMock,
            $this->serializerMock,
            $this->adyenLoggerMock,
            $this->webhookHelperMock
        );
    }

    public function testValidateReturnsFalseIfIpInvalid(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(false);

        $result = $this->acceptor->validate(['eventCode' => 'AUTHORISATION']);
        $this->assertFalse($result);
    }

    public function testValidateReturnsTrueIfNoHmac(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->configMock->method('getNotificationsHmacKey')->willReturn('');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->willReturn(false);

        $result = $this->acceptor->validate(['eventCode' => 'AUTHORISATION']);
        $this->assertTrue($result);
    }

    public function testValidateReturnsFalseIfHmacFails(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->configMock->method('getNotificationsHmacKey')->willReturn('secret');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->willReturn(true);
        $this->notificationReceiverMock->method('validateHmac')->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $result = $this->acceptor->validate(['eventCode' => 'AUTHORISATION']);
        $this->assertFalse($result);
    }

    public function testValidateReturnsTrueIfHmacSucceeds(): void
    {
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->configMock->method('getNotificationsHmacKey')->willReturn('secret');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->willReturn(true);
        $this->notificationReceiverMock->method('validateHmac')->willReturn(true);

        $result = $this->acceptor->validate(['eventCode' => 'AUTHORISATION']);
        $this->assertTrue($result);
    }

    public function testToNotificationListThrowsAuthenticationExceptionOnInvalidMode(): void
    {
        $this->configMock->method('isDemoMode')->willReturn(false);
        $this->notificationReceiverMock
            ->method('validateNotificationMode')
            ->willReturn(false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid notification mode.');

        $this->acceptor->getNotifications(['live' => 'invalid', 'notificationItems' => []]);
    }

    public function testToNotificationListThrowsOnInvalidNotification(): void
    {
        $this->configMock->method('isDemoMode')->willReturn(false);
        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->configMock->method('getNotificationsHmacKey')->willReturn('');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->willReturn(false);

        $this->acceptor = new StandardWebhookAcceptor(
            $this->configMock,
            $this->notificationFactoryMock,
            $this->notificationReceiverMock,
            $this->hmacSignatureMock,
            $this->serializerMock,
            $this->adyenLoggerMock,
            $this->webhookHelperMock
        );

        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->acceptor = $this->getMockBuilder(StandardWebhookAcceptor::class)
            ->setConstructorArgs([
                $this->configMock,
                $this->notificationFactoryMock,
                $this->notificationReceiverMock,
                $this->hmacSignatureMock,
                $this->serializerMock,
                $this->adyenLoggerMock,
                $this->webhookHelperMock
            ])
            ->onlyMethods(['validate'])
            ->getMock();

        $this->acceptor->method('validate')->willReturn(false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Notification failed authentication or validation.');

        $this->acceptor->getNotifications([
            'live' => 'true',
            'notificationItems' => [
                ['NotificationRequestItem' => ['eventCode' => 'AUTHORISATION']]
            ]
        ]);
    }

    public function testToNotificationListReturnsValidNotifications(): void
    {
        $payload = [
            'pspReference' => 'test_psp',
            'eventCode' => 'AUTHORISATION',
            'amount' => ['value' => 1000, 'currency' => 'EUR'],
            'additionalData' => ['key' => 'value']
        ];

        $notification = $this->getMockBuilder(Notification::class)
            ->onlyMethods(['isDuplicate', 'setCreatedAt', 'setUpdatedAt'])
            ->disableOriginalConstructor()
            ->getMock();

        $notification->method('isDuplicate')->willReturn(false);

        $this->notificationFactoryMock->method('create')->willReturn($notification);
        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);
        $this->configMock->method('isDemoMode')->willReturn(false);
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->willReturn(false);
        $this->serializerMock->method('serialize')->willReturn('{"key":"value"}');

        $result = $this->acceptor->getNotifications([
            'live' => 'true',
            'notificationItems' => [
                ['NotificationRequestItem' => $payload]
            ]
        ]);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Notification::class, $result[0]);
    }
}
