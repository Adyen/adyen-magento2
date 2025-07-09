<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Model\Webhook\StandardWebhookAcceptor;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(StandardWebhookAcceptor::class)]
class StandardWebhookAcceptorTest extends AbstractAdyenTestCase
{
    private StandardWebhookAcceptor $acceptor;

    private MockObject $configHelper;
    private MockObject $notificationFactory;
    private MockObject $notificationReceiver;
    private MockObject $hmacSignature;
    private MockObject $serializer;
    private MockObject $adyenLogger;
    private MockObject $webhookHelper;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(Config::class);
        $this->notificationFactory = $this->createMock(NotificationFactory::class);
        $this->notificationReceiver = $this->createMock(NotificationReceiver::class);
        $this->hmacSignature = $this->createMock(HmacSignature::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->webhookHelper = $this->createMock(Webhook::class);

        $this->acceptor = new StandardWebhookAcceptor(
            $this->configHelper,
            $this->notificationFactory,
            $this->notificationReceiver,
            $this->hmacSignature,
            $this->serializer,
            $this->adyenLogger,
            $this->webhookHelper
        );
    }

    public function testAuthenticate(): void
    {
        $payload = ['pspReference' => 'test123'];
        $merchantAccount = 'TestMerchant';
        $username = 'user';
        $password = 'pass';

        $this->configHelper->method('getMerchantAccount')->willReturn($merchantAccount);
        $this->configHelper->method('getNotificationsUsername')->willReturn($username);
        $this->configHelper->method('getNotificationsPassword')->willReturn($password);

        $this->notificationReceiver
            ->expects(self::once())
            ->method('isAuthenticated')
            ->with($payload, $merchantAccount, $username, $password)
            ->willReturn(true);

        self::assertTrue($this->acceptor->authenticate($payload));
    }

    public function testValidateReturnsFalseWhenIpInvalid(): void
    {
        $payload = [];

        $this->webhookHelper->method('isIpValid')->willReturn(false);

        self::assertFalse($this->acceptor->validate($payload));
    }

    public function testValidateReturnsFalseWhenHmacFails(): void
    {
        $payload = ['pspReference' => '123', 'eventCode' => 'AUTHORISATION'];

        $this->webhookHelper->method('isIpValid')->willReturn(true);
        $this->configHelper->method('getNotificationsHmacKey')->willReturn('test-key');
        $this->hmacSignature->method('isHmacSupportedEventCode')->willReturn(true);
        $this->notificationReceiver->method('validateHmac')->willReturn(false);
        $this->adyenLogger->expects(self::once())->method('addAdyenNotification');

        self::assertFalse($this->acceptor->validate($payload));
    }

    public function testValidateReturnsTrueIfNotDuplicate(): void
    {
        $payload = [
            'pspReference' => '123',
            'eventCode' => 'AUTHORISATION',
            'success' => 'true'
        ];

        $this->webhookHelper->method('isIpValid')->willReturn(true);
        $this->configHelper->method('getNotificationsHmacKey')->willReturn(null);

        $notification = $this->createMock(Notification::class);
        $notification->expects(self::once())->method('setPspreference');
        $notification->expects(self::once())->method('setEventCode');
        $notification->expects(self::once())->method('setSuccess');
        $notification->expects(self::once())->method('setOriginalReference');
        $notification->method('isDuplicate')->willReturn(false);

        $this->notificationFactory->method('create')->willReturn($notification);

        self::assertTrue($this->acceptor->validate($payload));
    }

    public function testToNotification(): void
    {
        $payload = [
            'pspReference' => '123',
            'originalReference' => '321',
            'merchantReference' => 'mr',
            'eventCode' => 'AUTHORISATION',
            'success' => 'true',
            'paymentMethod' => 'visa',
            'reason' => 'Approved',
            'done' => 'true',
            'amount' => ['value' => 1000, 'currency' => 'EUR'],
            'additionalData' => ['key' => 'value']
        ];

        $notification = $this->createMock(Notification::class);
        $this->notificationFactory->method('create')->willReturn($notification);

        $this->serializer->method('serialize')->willReturn('serialized_data');

        $notification->method('setCreatedAt');
        $notification->method('setUpdatedAt');

        $notification->expects(self::once())->method('setAdditionalData')->with('serialized_data');

        $result = $this->acceptor->toNotification($payload, 'live');
        self::assertInstanceOf(Notification::class, $result);
    }
}
