<?php

namespace Adyen\Payment\Test\Unit\Controller\Webhook;

use Adyen\Payment\Controller\Webhook\Index;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\IpAddress;
use Adyen\Payment\Helper\RateLimiter;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as Http;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\ResponseInterface;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private $controller;
    private $contextMock;
    private $requestMock;
    private $responseMock;
    private $notificationFactoryMock;
    private $adyenHelperMock;
    private $adyenLoggerMock;
    private $serializerMock;
    private $configHelperMock;
    private $ipAddressHelperMock;
    private $rateLimiterMock;
    private $hmacSignatureMock;
    private $notificationReceiverMock;
    private $remoteAddressMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->requestMock = $this->createMock(Http::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
        $this->notificationFactoryMock = $this->createMock(NotificationFactory::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->ipAddressHelperMock = $this->createMock(IpAddress::class);
        $this->rateLimiterMock = $this->createMock(RateLimiter::class);
        $this->hmacSignatureMock = $this->createMock(HmacSignature::class);
        $this->notificationReceiverMock = $this->createMock(NotificationReceiver::class);
        $this->remoteAddressMock = $this->createMock(RemoteAddress::class);

        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->method('getResponse')->willReturn($this->responseMock);

        $this->controller = new Index(
            $this->contextMock,
            $this->notificationFactoryMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->serializerMock,
            $this->configHelperMock,
            $this->ipAddressHelperMock,
            $this->rateLimiterMock,
            $this->hmacSignatureMock,
            $this->notificationReceiverMock,
            $this->remoteAddressMock,
            $this->requestMock
        );
    }

    public function testExecuteWithInvalidNotificationMode()
    {
        $this->requestMock->method('getContent')->willReturn(json_encode([
            'live' => 'invalid_mode',
            'notificationItems' => []
        ]));

        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->controller->execute();
    }

    public function testExecuteWithUnauthorizedRequest()
    {
        $this->requestMock->method('getContent')->willReturn(json_encode([
            'live' => 'true',
            'notificationItems' => [
                ['NotificationRequestItem' => ['pspReference' => 'test_psp']]
            ]
        ]));

        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);
        $this->controller->execute();

        $this->responseMock->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(401);
    }

    public function testExecuteWithValidNotification()
    {
        $this->requestMock->method('getContent')->willReturn(json_encode([
            'live' => 'true',
            'notificationItems' => [
                ['NotificationRequestItem' => [
                    'pspReference' => 'test_psp',
                    'merchantReference' => 'test_merchant',
                    'eventCode' => 'AUTHORISATION',
                    'success' => 'true',
                    'amount' => ['value' => 1000, 'currency' => 'EUR'],
                    'additionalData' => []
                ]]
            ]
        ]));

        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);
        $this->notificationReceiverMock->method('isAuthenticated')->willReturn(true);
        $this->ipAddressHelperMock->method('isIpAddressValid')->willReturn(true);
        $this->hmacSignatureMock->method('isHmacSupportedEventCode')->willReturn(true);
        $this->notificationReceiverMock->method('validateHmac')->willReturn(true);

        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->expects($this->once())->method('setLive')->with('true');
        $notificationMock->expects($this->once())->method('save');

        $this->notificationFactoryMock->method('create')->willReturn($notificationMock);

        $this->controller->execute();

        $this->responseMock->expects($this->never())
            ->method('setHttpResponseCode')
            ->with(401);
    }
}
