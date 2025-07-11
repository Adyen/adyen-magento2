<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Controller\Webhook;

use Adyen\Payment\Controller\Webhook\Index;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Model\Webhook\WebhookAcceptorType;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

#[CoversClass(Index::class)]
final class IndexTest extends AbstractAdyenTestCase
{
    private Index $controller;

    private HttpRequest $requestMock;
    private Response $responseMock;
    private AdyenLogger $adyenLoggerMock;
    private Config $configHelperMock;
    private NotificationReceiver $notificationReceiverMock;
    private WebhookAcceptorFactory $webhookAcceptorFactoryMock;

    protected function setUp(): void
    {
        $this->requestMock = $this->getMockBuilder(HttpRequest::class)
            ->onlyMethods(['getContent', 'isPost', 'setParam', 'getHeaders'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->onlyMethods(['setHeader', 'setBody', 'clearHeader', 'setHttpResponseCode'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->notificationReceiverMock = $this->createMock(NotificationReceiver::class);
        $this->webhookAcceptorFactoryMock = $this->createMock(WebhookAcceptorFactory::class);
        $adyenHelperMock = $this->createMock(Data::class);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $contextMock->method('getResponse')->willReturn($this->responseMock);

        $this->controller = new Index(
            $contextMock,
            $this->adyenLoggerMock,
            $this->configHelperMock,
            $this->notificationReceiverMock,
            $this->webhookAcceptorFactoryMock,
            $adyenHelperMock
        );
    }

    public function testExecuteProcessesValidStandardWebhook(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $notification = $this->getMockBuilder(Notification::class)
            ->onlyMethods(['getId', 'save'])
            ->disableOriginalConstructor()
            ->getMock();

        $notification->method('getId')->willReturn('123');
        $notification->expects($this->once())->method('save');

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('toNotificationList')->willReturn([$notification]);

        $payload = [
            'notificationItems' => [
                ['NotificationRequestItem' => ['eventCode' => 'AUTHORISATION']]
            ]
        ];

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));
        $this->webhookAcceptorFactoryMock->method('getAcceptor')->with(WebhookAcceptorType::STANDARD)->willReturn($acceptorMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenResult')
            ->with('Notification 123 is accepted');

        $this->responseMock->expects($this->once())->method('clearHeader')->with('Content-Type')->willReturnSelf();
        $this->responseMock->expects($this->once())->method('setHeader')->with('Content-Type', 'text/html')->willReturnSelf();
        $this->responseMock->expects($this->once())->method('setBody')->with('[accepted]')->willReturnSelf();

        $this->controller->execute();
    }

    public function testExecuteProcessesTokenLifecycleWebhook(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $notification = $this->getMockBuilder(Notification::class)
            ->onlyMethods(['getId', 'save'])
            ->disableOriginalConstructor()
            ->getMock();

        $notification->method('getId')->willReturn('token-456');
        $notification->expects($this->once())->method('save');

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('toNotificationList')->willReturn([$notification]);

        $payload = ['type' => 'token.created'];

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));
        $this->webhookAcceptorFactoryMock->method('getAcceptor')->with(WebhookAcceptorType::TOKEN)->willReturn($acceptorMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenResult')
            ->with('Notification token-456 is accepted');

        $this->responseMock->expects($this->once())->method('clearHeader')->with('Content-Type')->willReturnSelf();
        $this->responseMock->expects($this->once())->method('setHeader')->with('Content-Type', 'text/html')->willReturnSelf();
        $this->responseMock->expects($this->once())->method('setBody')->with('[accepted]')->willReturnSelf();

        $this->controller->execute();
    }

    public function testExecuteThrowsLocalizedExceptionOnUnexpectedPayload(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $payload = ['foo' => 'bar']; // malformed payload

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Webhook processing failed: Unable to determine webhook type from payload.');

        $this->controller->execute();
    }

    public function testExecuteReturns401IfAuthenticationFails(): void
    {
        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

        $this->requestMock->method('getContent')->willReturn('{}');

        $this->responseMock->expects($this->once())->method('setHttpResponseCode')->with(401);
        $this->responseMock->expects($this->once())->method('setBody')->with('Unauthorized');

        $this->controller->execute();
    }

    public function testExecuteThrowsLocalizedExceptionOnEmptyBody(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $this->requestMock->method('getContent')->willReturn('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Empty request body.');

        $this->controller->execute();
    }

    public function testExecuteThrowsLocalizedExceptionOnInvalidJson(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $this->requestMock->method('getContent')->willReturn('invalid-json');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid JSON payload.');

        $this->controller->execute();
    }

    public function testExecuteReturns401OnAuthenticationException(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $payload = [
            'notificationItems' => [
                ['NotificationRequestItem' => ['eventCode' => 'AUTHORISATION']]
            ]
        ];

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('toNotificationList')->willThrowException(
            new \Adyen\Payment\Exception\AuthenticationException('Auth error')
        );

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));
        $this->webhookAcceptorFactoryMock->method('getAcceptor')->willReturn($acceptorMock);

        $this->responseMock->expects($this->once())->method('setHttpResponseCode')->with(401);
        $this->responseMock->expects($this->once())->method('setBody')->with('Unauthorized');

        $this->controller->execute();
    }

    public function testExecuteThrowsLocalizedExceptionOnGenericError(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $payload = [
            'notificationItems' => [
                ['NotificationRequestItem' => ['eventCode' => 'AUTHORISATION']]
            ]
        ];

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('toNotificationList')->willThrowException(new \RuntimeException('something went wrong'));

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));
        $this->webhookAcceptorFactoryMock->method('getAcceptor')->willReturn($acceptorMock);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Webhook processing failed: something went wrong');

        $this->controller->execute();
    }

}
