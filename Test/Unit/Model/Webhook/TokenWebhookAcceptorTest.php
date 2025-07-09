<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Model\Webhook\TokenWebhookAcceptor;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(TokenWebhookAcceptor::class)]
class TokenWebhookAcceptorTest extends AbstractAdyenTestCase
{
    private TokenWebhookAcceptor $acceptor;

    private MockObject $notificationFactory;
    private MockObject $serializer;
    private MockObject $adyenLogger;
    private MockObject $webhookHelper;

    protected function setUp(): void
    {
        $this->notificationFactory = $this->createMock(NotificationFactory::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->webhookHelper = $this->createMock(Webhook::class);

        $this->acceptor = new TokenWebhookAcceptor(
            $this->notificationFactory,
            $this->serializer,
            $this->adyenLogger,
            $this->webhookHelper
        );
    }

    public function testAuthenticateAlwaysReturnsTrue(): void
    {
        $payload = ['key' => 'value'];
        self::assertTrue($this->acceptor->authenticate($payload));
    }

    public function testValidateFailsWhenIpIsInvalid(): void
    {
        $this->webhookHelper->method('isIpValid')->willReturn(false);
        self::assertFalse($this->acceptor->validate([]));
    }

    public function testValidateFailsWhenRequiredFieldsMissing(): void
    {
        $this->webhookHelper->method('isIpValid')->willReturn(true);
        $this->adyenLogger
            ->expects(self::once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Missing required field'),
                $this->isType('array')
            );

        $result = $this->acceptor->validate([
            'eventId' => 'evt_123',
            // Missing nested data fields
        ]);

        self::assertFalse($result);
    }

    public function testValidatePassesWithAllRequiredFields(): void
    {
        $payload = [
            'eventId' => 'evt_123',
            'type' => 'TOKEN',
            'data' => [
                'storedPaymentMethodId' => 'spm_456',
                'type' => 'visa',
                'shopperReference' => 'shopper_789',
                'merchantAccount' => 'Merchant123'
            ]
        ];

        $this->webhookHelper->method('isIpValid')->willReturn(true);
        $this->webhookHelper
            ->expects(self::once())
            ->method('isMerchantAccountValid')
            ->with('Merchant123', $payload, 'token webhook')
            ->willReturn(true);

        self::assertTrue($this->acceptor->validate($payload));
    }

    public function testToNotificationBuildsCorrectly(): void
    {
        $payload = [
            'eventId' => 'evt_001',
            'type' => 'TOKEN',
            'data' => [
                'storedPaymentMethodId' => 'spm_001',
                'type' => 'mc',
                'shopperReference' => 'shopper_xyz',
                'merchantAccount' => 'MerchantABC'
            ]
        ];

        $notification = $this->createMock(Notification::class);
        $this->notificationFactory->method('create')->willReturn($notification);

        $this->serializer
            ->expects(self::once())
            ->method('serialize')
            ->with($payload)
            ->willReturn('serialized_payload');

        $notification->expects(self::once())->method('setPspreference')->with('spm_001');
        $notification->expects(self::once())->method('setOriginalReference')->with('evt_001');
        $notification->expects(self::once())->method('setMerchantReference')->with('shopper_xyz');
        $notification->expects(self::once())->method('setEventCode')->with('TOKEN');
        $notification->expects(self::once())->method('setPaymentMethod')->with('mc');
        $notification->expects(self::once())->method('setLive')->with('live');
        $notification->expects(self::once())->method('setSuccess')->with('true');
        $notification->expects(self::once())->method('setReason')->with('Token lifecycle event');
        $notification->expects(self::once())->method('setAdditionalData')->with('serialized_payload');
        $notification->method('setCreatedAt');
        $notification->method('setUpdatedAt');

        $result = $this->acceptor->toNotification($payload, 'live');
        self::assertInstanceOf(Notification::class, $result);
    }
}
