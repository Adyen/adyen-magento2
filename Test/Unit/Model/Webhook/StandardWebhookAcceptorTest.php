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
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class StandardWebhookAcceptorTest extends AbstractAdyenTestCase
{
    private ?StandardWebhookAcceptor $acceptor;
    private Config|MockObject $configMock;
    private NotificationFactory|MockObject $notificationFactoryMock;
    private NotificationReceiver|MockObject $notificationReceiverMock;
    private HmacSignature|MockObject $hmacSignatureMock;
    private SerializerInterface|MockObject $serializerMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private Webhook|MockObject $webhookHelperMock;

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
            $this->notificationFactoryMock,
            $this->adyenLoggerMock,
            $this->webhookHelperMock,
            $this->configMock,
            $this->notificationReceiverMock,
            $this->hmacSignatureMock,
            $this->serializerMock
        );
    }

    protected  function tearDown(): void
    {
        $this->acceptor = null;
    }

    public function testToNotificationListReturnsValidNotifications(): void
    {
        $payload = [
            'pspReference' => 'test_psp',
            'eventCode' => 'AUTHORISATION',
            'amount' => ['value' => 1000, 'currency' => 'EUR'],
            'additionalData' => ['key' => 'value'],
            'merchantAccountCode' => 'MOCK_MERCHANT_ACCOUNT'
        ];

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);

        $this->notificationFactoryMock->method('create')->willReturn($notification);
        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);

        $this->configMock->method('isDemoMode')->willReturn(false);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(true);

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

    public function testValidateThrowsExceptionOnInvalidNotificationMode(): void
    {
        $this->expectException(InvalidDataException::class);

        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(false);
        $this->webhookHelperMock->method('isMerchantAccountValid')->willReturn(false);

        $this->acceptor->getNotifications([
            'live' => 'true',
            'notificationItems' => [
                ['NotificationRequestItem' => ['MOCK_PAYLOAD']]
            ]
        ]);
    }

    public function testValidateThrowsExceptionOnInvalidMerchantAccount(): void
    {
        $this->expectException(InvalidDataException::class);

        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);

        $this->acceptor->getNotifications([
            'live' => 'true',
            'notificationItems' => [
                ['NotificationRequestItem' => ['merchantAccountCode' =>  'MOCK_MERCHANT_ACCOUNT']]
            ]
        ]);
    }

    public function testValidateThrowsExceptionOnInvalidHmacKey(): void
    {
        $this->expectException(InvalidDataException::class);

        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);
        $this->configMock->method('getNotificationsHmacKey')->willReturn('MOCK_HMAC_KEY');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->willReturn(true);
        $this->notificationReceiverMock->method('validateHmac')->willReturn(false);

        $this->acceptor->getNotifications([
            'live' => 'true',
            'notificationItems' => [
                ['NotificationRequestItem' => ['merchantAccountCode' =>  'MOCK_MERCHANT_ACCOUNT']]
            ]
        ]);
    }
}
