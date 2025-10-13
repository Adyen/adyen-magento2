<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Controller\Webhook;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Controller\Webhook\Index;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Webhook\TokenWebhookAcceptor;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Model\Webhook\WebhookAcceptorType;
use Adyen\Payment\Model\Notification;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NotFoundException;
use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class IndexTest extends AbstractAdyenTestCase
{
    private ?Index $controller;
    private HttpRequest $requestMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private Config|MockObject $configHelperMock;
    private Webhook|MockObject $webhookHelperMock;
    private ResultFactory|MockObject $resultFactoryMock;
    private ResultInterface|MockObject $resultMock;
    private WebhookAcceptorFactory|MockObject $webhookAcceptorFactoryMock;
    private AdyenNotificationRepositoryInterface|MockObject $adyenNotificationRepositoryMock;

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->requestMock = $this->createMock(Http::class);

        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->webhookHelperMock = $this->createMock(Webhook::class);
        $this->webhookAcceptorFactoryMock = $this->createMock(WebhookAcceptorFactory::class);
        $this->adyenNotificationRepositoryMock = $this->createMock(AdyenNotificationRepositoryInterface::class);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($this->requestMock);

        $this->resultMock = $this->createMock(Raw::class);
        $this->resultFactoryMock = $this->createMock(ResultFactory::class);
        $this->resultFactoryMock->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->resultMock);

        $this->controller = new Index(
            $contextMock,
            $this->adyenLoggerMock,
            $this->configHelperMock,
            $this->webhookAcceptorFactoryMock,
            $this->webhookHelperMock,
            $this->resultFactoryMock,
            $this->adyenNotificationRepositoryMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->controller = null;
    }

    public static function dataProviderProcessValidWebhook(): array
    {
        return [
            [
                'payload' => [
                    'notificationItems' => [
                        ['NotificationRequestItem' => ['eventCode' => 'AUTHORISATION']]
                    ]
                ],
                'eventType' => WebhookAcceptorType::STANDARD
            ],
            [
                'payload' => [
                    'type' => 'token.created'
                ],
                'eventType' => WebhookAcceptorType::TOKEN
            ]
        ];
    }

    /**
     * @dataProvider dataProviderProcessValidWebhook
     *
     * @param $payload
     * @param $eventType
     * @return void
     * @throws Exception
     * @throws NotFoundException
     */
    public function testExecuteProcessesValidWebhook($payload, $eventType): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $notification = $this->createMock(Notification::class);
        $notification->method('getId')->willReturn('123');

        $this->adyenNotificationRepositoryMock->method('save')
            ->with($notification)
            ->willReturn($notification);

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('getNotifications')->willReturn([$notification]);

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));
        $this->webhookAcceptorFactoryMock->method('getAcceptor')
            ->with($eventType)
            ->willReturn($acceptorMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenResult')
            ->with('Notification 123 is accepted');

        $this->webhookHelperMock->method('isIpValid')->willReturn(true);

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(200);
        $this->resultMock->expects($this->once())->method('setContents')->with('[accepted]');
        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }

    /**
     * @dataProvider dataProviderProcessValidWebhook
     *
     * @param $payload
     * @param $eventType
     * @return void
     * @throws Exception
     * @throws NotFoundException
     */
    public function testExecuteProcessesDuplicateWebhook($payload, $eventType): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $notification = $this->createMock(Notification::class);
        $notification->method('getPspreference')->willReturn('ABC12345678XYZ');
        $notification->method('isDuplicate')->willReturn(true);

        $this->adyenNotificationRepositoryMock->expects($this->never())->method('save');

        $acceptorMock = $this->createMock(WebhookAcceptorInterface::class);
        $acceptorMock->method('getNotifications')->willReturn([$notification]);

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));
        $this->webhookAcceptorFactoryMock->method('getAcceptor')
            ->with($eventType)
            ->willReturn($acceptorMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenResult')
            ->with('Duplicate notification with pspReference ABC12345678XYZ has been skipped.');

        $this->webhookHelperMock->method('isIpValid')->willReturn(true);

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(200);
        $this->resultMock->expects($this->once())->method('setContents')->with('[accepted]');

        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }

    public function testWebhookUnidentifiedEventType(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');
        $this->webhookHelperMock->method('isIpValid')->willReturn(true);

        $payload = ['foo' => 'bar']; // malformed payload

        $this->requestMock->method('getContent')->willReturn(json_encode($payload));

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(400);
        $this->resultMock->expects($this->once())->method('setContents')
            ->with('The request does not contain a valid webhook!');
        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }

    public function testAuthenticationFails(): void
    {
        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

        $this->requestMock->method('getContent')->willReturn('{}');

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(401);
        $this->resultMock->expects($this->once())->method('setContents')->with('Unauthorized');
        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }

    public function testExecuteOnEmptyBody(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $this->requestMock->method('getContent')->willReturn('');

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(400);
        $this->resultMock->expects($this->once())->method('setContents')
            ->with('The request does not contain a valid webhook!');
        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }

    public function testExecuteOnInvalidJson(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $this->requestMock->method('getContent')->willReturn('invalid-json');

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(400);
        $this->resultMock->expects($this->once())->method('setContents')
            ->with('The request does not contain a valid webhook!');
        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }

    public function testExecuteOnInvalidIpOrigin(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $this->requestMock->method('getContent')->willReturn('{"foo":"bar"}');
        $this->webhookHelperMock->method('isIpValid')->willReturn(false);

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(401);
        $this->resultMock->expects($this->once())->method('setContents')->with('Unauthorized');
        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }

    public function testExecuteOnGenericError(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';

        $this->configHelperMock->method('getNotificationsUsername')->willReturn('user');
        $this->configHelperMock->method('getNotificationsPassword')->willReturn('pass');

        $this->requestMock->method('getContent')->willthrowException(new \Exception());

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->resultMock->expects($this->once())->method('setStatusHeader')->with(500);
        $this->resultMock->expects($this->once())->method('setContents')
            ->with('An error occurred while handling this webhook!');
        $this->assertInstanceOf(ResultInterface::class, $this->controller->execute());
    }
}
