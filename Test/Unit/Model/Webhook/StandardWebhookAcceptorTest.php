<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Model\Webhook\StandardWebhookAcceptor;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\HMACKeyValidationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class StandardWebhookAcceptorTest extends AbstractAdyenTestCase
{
    private ?StandardWebhookAcceptor $acceptor = null;

    /** @var NotificationFactory|MockObject */
    private $notificationFactoryMock;

    /** @var SerializerInterface|MockObject */
    private $serializerMock;

    /** @var AdyenLogger|MockObject */
    private $adyenLoggerMock;

    /** @var Webhook|MockObject */
    private $webhookHelperMock;

    /** @var Config|MockObject */
    private $configHelperMock;

    /** @var NotificationReceiver|MockObject */
    private $notificationReceiverMock;

    /** @var HmacSignature|MockObject */
    private $hmacSignatureMock;

    /** @var OrderHelper|MockObject */
    private $orderHelperMock;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->notificationFactoryMock  = $this->createMock(NotificationFactory::class);
        $this->serializerMock           = $this->createMock(SerializerInterface::class);
        $this->adyenLoggerMock          = $this->createMock(AdyenLogger::class);
        $this->webhookHelperMock        = $this->createMock(Webhook::class);
        $this->configHelperMock         = $this->createMock(Config::class);
        $this->notificationReceiverMock = $this->createMock(NotificationReceiver::class);
        $this->hmacSignatureMock        = $this->createMock(HmacSignature::class);
        $this->orderHelperMock          = $this->createMock(OrderHelper::class);

        $this->serializerMock->method('serialize')
            ->willReturnCallback(static fn($v) => json_encode($v));

        $this->acceptor = new StandardWebhookAcceptor(
            $this->notificationFactoryMock,
            $this->adyenLoggerMock,
            $this->webhookHelperMock,
            $this->configHelperMock,
            $this->notificationReceiverMock,
            $this->hmacSignatureMock,
            $this->serializerMock,
            $this->orderHelperMock
        );

        // Sanity: class implements interface
        $this->assertInstanceOf(WebhookAcceptorInterface::class, $this->acceptor);
    }

    protected function tearDown(): void
    {
        $this->acceptor = null;
    }

    /** ------------------------- Tests ------------------------- */

    /**
     * Happy path: one notification item, env OK, merchant OK, HMAC OK, not duplicate.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws Exception
     * @throws HMACKeyValidationException
     * @throws InvalidDataException
     */
    public function testGetNotificationsReturnsNotification(): void
    {
        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];

        // Order scope
        $orderMock = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId'])
            ->getMock();
        $orderMock->method('getStoreId')->willReturn(10);

        $this->orderHelperMock->expects($this->once())
            ->method('getOrderByIncrementId')
            ->with($item['merchantReference'])
            ->willReturn($orderMock);

        // Env mode validation (live = 'false' -> demo must be true)
        $this->configHelperMock->expects($this->once())
            ->method('isDemoMode')
            ->with(10)
            ->willReturn(true);

        $this->notificationReceiverMock->expects($this->once())
            ->method('validateNotificationMode')
            ->with('false', true)
            ->willReturn(true);

        // Merchant validation with storeId
        $this->webhookHelperMock->expects($this->once())
            ->method('isMerchantAccountValid')
            ->with('TestMerchant', $item, 'webhook', 10)
            ->willReturn(true);

        // HMAC: supported + valid
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn('deadbeef');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->with($item)->willReturn(true);
        $this->notificationReceiverMock->expects($this->once())
            ->method('validateHmac')
            ->with($item, 'deadbeef')
            ->willReturn(true);

        // Notification creation (not duplicate)
        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, $result[0]);
    }

    /**
     * Missing merchantReference triggers InvalidDataException early.
     */
    public function testGetNotificationsThrowsIfMerchantReferenceMissing(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();
        unset($payload['notificationItems'][0]['NotificationRequestItem']['merchantReference']);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * Invalid environment mode -> InvalidDataException.
     */
    public function testValidateThrowsInvalidDataExceptionWhenNotificationModeInvalid(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];

        // No order found -> storeId = null is fine
        $this->orderHelperMock->method('getOrderByIncrementId')->willReturn(null);

        $this->configHelperMock->expects($this->once())
            ->method('isDemoMode')
            ->with(null)
            ->willReturn(false);

        $this->notificationReceiverMock->expects($this->once())
            ->method('validateNotificationMode')
            ->with('false', false)
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * Merchant account mismatch -> InvalidDataException.
     */
    public function testValidateThrowsInvalidDataExceptionWhenMerchantAccountInvalid(): void
    {
        $this->expectException(InvalidDataException::class);

        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];

        // Env OK
        $this->orderHelperMock->method('getOrderByIncrementId')->willReturn(null);
        $this->configHelperMock->method('isDemoMode')->with(null)->willReturn(true);
        $this->notificationReceiverMock->method('validateNotificationMode')->with('false', true)->willReturn(true);

        // Merchant invalid
        $this->webhookHelperMock->expects($this->once())
            ->method('isMerchantAccountValid')
            ->with('TestMerchant', $item, 'webhook', null)
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * HMAC supported & invalid -> AuthenticationException.
     *
     * @throws AlreadyExistsException
     * @throws AuthenticationException
     * @throws HMACKeyValidationException
     * @throws InvalidDataException
     */
    public function testValidateThrowsAuthenticationExceptionWhenHmacInvalid(): void
    {
        $this->expectException(AuthenticationException::class);

        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];

        // Env OK & merchant OK
        $this->orderHelperMock->method('getOrderByIncrementId')->willReturn(null);
        $this->configHelperMock->method('isDemoMode')->with(null)->willReturn(true);
        $this->notificationReceiverMock->method('validateNotificationMode')->with('false', true)->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->with('TestMerchant', $item, 'webhook', null)->willReturn(true);

        // HMAC present+supported but invalid
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn('deadbeef');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->with($item)->willReturn(true);
        $this->notificationReceiverMock->expects($this->once())
            ->method('validateHmac')
            ->with($item, 'deadbeef')
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->acceptor->getNotifications($payload);
    }

    /**
     * Duplicate notification -> AlreadyExistsException.
     */
    public function testToNotificationThrowsAlreadyExistsExceptionOnDuplicate(): void
    {
        $this->expectException(AlreadyExistsException::class);

        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];

        // Env OK & merchant OK
        $this->orderHelperMock->method('getOrderByIncrementId')->willReturn(null);
        $this->configHelperMock->method('isDemoMode')->with(null)->willReturn(true);
        $this->notificationReceiverMock->method('validateNotificationMode')->with('false', true)->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->with('TestMerchant', $item, 'webhook', null)->willReturn(true);

        // HMAC check disabled (no key) to keep test focused
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn(null);
        $this->hmacSignatureMock->expects($this->never())->method('isHmacSupportedEventCode');
        $this->notificationReceiverMock->expects($this->never())->method('validateHmac');

        // Duplicate
        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(true);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $this->acceptor->getNotifications($payload);
    }

    /**
     * If HMAC key missing or event not supported, HMAC is not validated.
     * Here we simulate "event not supported".
     */
    public function testHmacNotValidatedWhenEventNotSupported(): void
    {
        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];

        // Env OK & merchant OK
        $this->orderHelperMock->method('getOrderByIncrementId')->willReturn(null);
        $this->configHelperMock->method('isDemoMode')->with(null)->willReturn(true);
        $this->notificationReceiverMock->method('validateNotificationMode')->with('false', true)->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->with('TestMerchant', $item, 'webhook', null)->willReturn(true);

        // HMAC key present but event not supported -> skip validateHmac
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn('deadbeef');
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->with($item)->willReturn(false);
        $this->notificationReceiverMock->expects($this->never())->method('validateHmac');

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, $result[0]);
    }

    /**
     * If HMAC key is missing entirely, we skip HMAC validation.
     */
    public function testHmacNotValidatedWhenKeyMissing(): void
    {
        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];

        // Env OK & merchant OK
        $this->orderHelperMock->method('getOrderByIncrementId')->willReturn(null);
        $this->configHelperMock->method('isDemoMode')->with(null)->willReturn(true);
        $this->notificationReceiverMock->method('validateNotificationMode')->with('false', true)->willReturn(true);
        $this->webhookHelperMock->method('isMerchantAccountValid')->with('TestMerchant', $item, 'webhook', null)->willReturn(true);

        // HMAC key missing -> skip validateHmac entirely
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn(null);
        $this->hmacSignatureMock->expects($this->never())->method('isHmacSupportedEventCode');
        $this->notificationReceiverMock->expects($this->never())->method('validateHmac');

        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, $result[0]);
    }

    public function testLogsAndContinuesWhenOrderLoadThrows(): void
    {
        $payload = $this->getValidPayload();
        $item = $payload['notificationItems'][0]['NotificationRequestItem'];
        $merchantRef = $item['merchantReference'];

        // Simulate failure while loading order (hits the catch block)
        $this->orderHelperMock->expects($this->once())
            ->method('getOrderByIncrementId')
            ->with($merchantRef)
            ->willThrowException(new \RuntimeException('DB down'));

        // Expect a log line with the formatted message and payload
        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->callback(function ($msg) use ($merchantRef) {
                    return str_contains($msg, "Could not load order for reference {$merchantRef}: DB down");
                }),
                $this->equalTo($payload)
            );

        // Since order load failed, storeId should be null in the rest of the flow
        $this->configHelperMock->expects($this->once())
            ->method('isDemoMode')
            ->with(null)
            ->willReturn(true);

        $this->notificationReceiverMock->expects($this->once())
            ->method('validateNotificationMode')
            ->with('false', true)
            ->willReturn(true);

        $this->webhookHelperMock->expects($this->once())
            ->method('isMerchantAccountValid')
            ->with('TestMerchant', $item, 'webhook', null)
            ->willReturn(true);

        // Keep HMAC path simple: no key => no validation
        $this->configHelperMock->method('getNotificationsHmacKey')->willReturn(null);
        $this->hmacSignatureMock->expects($this->never())->method('isHmacSupportedEventCode');
        $this->notificationReceiverMock->expects($this->never())->method('validateHmac');

        // Create a non-duplicate notification so the flow completes
        $notification = $this->createMock(Notification::class);
        $notification->method('isDuplicate')->willReturn(false);
        $this->notificationFactoryMock->method('create')->willReturn($notification);

        $result = $this->acceptor->getNotifications($payload);

        $this->assertCount(1, $result);
        $this->assertSame($notification, $result[0]);
    }

    private function getValidPayload(): array
    {
        return [
            'live' => 'false',
            'notificationItems' => [
                [
                    'NotificationRequestItem' => [
                        'merchantAccountCode' => 'TestMerchant',
                        'merchantReference'   => '100000001',
                        'pspReference'        => 'psp-123',
                        'originalReference'   => 'psp-orig-001',
                        'eventCode'           => 'AUTHORISATION',
                        'success'             => 'true',
                        'paymentMethod'       => 'visa',
                        'reason'              => 'Authorised',
                        'done'                => true,
                        'amount'              => ['value' => 1000, 'currency' => 'EUR'],
                        'additionalData'      => ['some' => 'data'],
                    ]
                ]
            ]
        ];
    }
}
