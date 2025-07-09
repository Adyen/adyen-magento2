<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Controller\Webhook;

use Adyen\Payment\Controller\Webhook\Index;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\HTTP\PhpEnvironment\Response;

#[CoversClass(Index::class)]
final class IndexTest extends AbstractAdyenTestCase
{
    private Index $controller;

    private $requestMock;
    private $responseMock;
    private $contextMock;
    private $adyenLoggerMock;
    private $configHelperMock;
    private $notificationReceiverMock;
    private $webhookAcceptorFactoryMock;

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        $this->requestMock = $this->getMockBuilder(HttpRequest::class)
            ->onlyMethods(['getContent'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->onlyMethods(['setHeader', 'setBody', 'clearHeader', 'setStatusHeader', 'setHttpResponseCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->notificationReceiverMock = $this->createMock(NotificationReceiver::class);
        $this->webhookAcceptorFactoryMock = $this->createMock(WebhookAcceptorFactory::class);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->method('getResponse')->willReturn($this->responseMock);

        $this->controller = new Index(
            $this->contextMock,
            $this->adyenLoggerMock,
            $this->configHelperMock,
            $this->notificationReceiverMock,
            $this->webhookAcceptorFactoryMock
        );
    }

    public function testExecuteThrowsExceptionAndLogsWhenProcessingFails(): void
    {
        $payload = [
            'live' => 'true',
            'notificationItems' => [[
                'NotificationRequestItem' => ['eventCode' => 'AUTHORISATION']
            ]]
        ];

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('authenticate')->willReturn(true);
        $acceptorMock->method('validate')->willReturn(true);
        $acceptorMock->method('toNotification')->willThrowException(new Exception('fail'));

        $this->configHelperMock->method('isDemoMode')->willReturn(false);
        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);
        $this->webhookAcceptorFactoryMock->method('getAcceptor')->willReturn($acceptorMock);
        $this->requestMock->method('getContent')->willReturn(json_encode($payload));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Webhook processing failed: fail');

        $this->controller->execute();
    }

    public function testExecuteProcessesValidNotification(): void
    {
        $payload = [
            'live' => 'true',
            'notificationItems' => [[
                'NotificationRequestItem' => ['eventCode' => 'AUTHORISATION']
            ]]
        ];

        $notification = $this->getMockBuilder(Notification::class)
            ->onlyMethods(['getId', 'save'])
            ->disableOriginalConstructor()
            ->getMock();

        $notification->method('getId')->willReturn('12345');
        $notification->expects($this->once())->method('save');

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('authenticate')->willReturn(true);
        $acceptorMock->method('validate')->willReturn(true);
        $acceptorMock->method('toNotification')->willReturn($notification);

        $this->configHelperMock->method('isDemoMode')->willReturn(false);
        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);
        $this->webhookAcceptorFactoryMock->method('getAcceptor')->willReturn($acceptorMock);
        $this->requestMock->method('getContent')->willReturn(json_encode($payload));

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenResult')
            ->with("Notification 12345 is accepted");

        $this->responseMock->expects($this->once())
            ->method('clearHeader')
            ->with('Content-Type')
            ->willReturn($this->responseMock);

        $this->responseMock->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'text/html')
            ->willReturn($this->responseMock);

        $this->responseMock->expects($this->once())
            ->method('setBody')
            ->with('[accepted]')
            ->willReturn($this->responseMock);

        $this->responseMock->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'text/html');

        $this->responseMock->expects($this->once())
            ->method('setBody')
            ->with('[accepted]');
        $this->controller->execute();
    }

    public function testGetWebhookTypeReturnsStandard(): void
    {
        $payload = ['eventCode' => 'AUTHORISATION'];
        $ref = new \ReflectionClass(Index::class);
        $method = $ref->getMethod('getWebhookType');
        $method->setAccessible(true);
        $this->assertSame(WebhookAcceptorInterface::TYPE_STANDARD, $method->invoke($this->controller, $payload));
    }

    public function testGetWebhookTypeReturnsToken(): void
    {
        $payload = ['type' => 'token.created'];
        $ref = new \ReflectionClass(Index::class);
        $method = $ref->getMethod('getWebhookType');
        $method->setAccessible(true);
        $this->assertSame(WebhookAcceptorInterface::TYPE_TOKEN, $method->invoke($this->controller, $payload));
    }

    public function testGetWebhookTypeThrowsForUnknownPayload(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unable to determine webhook type from payload.');

        $payload = ['foo' => 'bar'];
        $ref = new \ReflectionClass(Index::class);
        $method = $ref->getMethod('getWebhookType');
        $method->setAccessible(true);
        $method->invoke($this->controller, $payload);
    }

    public function testIsNotificationModeValidReturnsFalseWhenLiveMissing(): void
    {
        $payload = [];
        $ref = new \ReflectionClass(Index::class);
        $method = $ref->getMethod('isNotificationModeValid');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke($this->controller, $payload));
    }

    public function testIsNotificationModeValidReturnsTrueWhenMatched(): void
    {
        $payload = ['live' => 'true'];
        $this->configHelperMock->method('isDemoMode')->willReturn(false);
        $this->notificationReceiverMock->method('validateNotificationMode')->willReturn(true);

        $ref = new \ReflectionClass(Index::class);
        $method = $ref->getMethod('isNotificationModeValid');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($this->controller, $payload));
    }
}
