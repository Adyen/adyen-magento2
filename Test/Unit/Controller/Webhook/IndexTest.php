<?php

namespace Adyen\Payment\Test\Unit\Controller\Webhook;

use Adyen\Payment\Controller\Webhook\Index;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\IpAddress;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Helper\RateLimiter;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class IndexTest extends AbstractAdyenTestCase
{
    /**
     * @var Context|\PHPUnit\Framework\MockObject\MockObject
     */
    private $contextMock;

    /**
     * @var RequestInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $requestMock;

    /**
     * @var ResponseInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $responseMock;

    /**
     * @var JsonFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultJsonFactoryMock;

    /**
     * @var Json|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultJsonMock;

    /**
     * @var Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adyenHelperMock;

    /**
     * @var NotificationFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $notificationHelperMock;

    /**
     * @var SerializerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $serializerMock;

    /**
     * @var AdyenLogger|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adyenLoggerMock;

    /**
     * @var Index
     */
    private $indexController;

    protected function setUp(): void
    {
        $this->contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock = $this->getMockBuilder(RequestInterface::class)
            ->getMockForAbstractClass();
        $this->responseMock = $this->getMockBuilder(ResponseInterface::class)
            ->getMockForAbstractClass();
        $this->resultJsonFactoryMock = $this->getMockBuilder(JsonFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->resultJsonMock = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adyenHelperMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->notificationHelperMock = $this->createGeneratedMock(NotificationFactory::class, [
            'create'
        ]);
        $this->serializerMock = $this->getMockBuilder(SerializerInterface::class)
            ->getMockForAbstractClass();
        $this->adyenLoggerMock = $this->getMockBuilder(AdyenLogger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->ipAddressHelperMock = $this->getMockBuilder(IpAddress::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelperMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->rateLimiterHelperMock = $this->getMockBuilder(RateLimiter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->hmacSignatureMock = $this->getMockBuilder(HmacSignature::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->notificationReceiverMock = $this->getMockBuilder(NotificationReceiver::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->remoteAddressMock = $this->getMockBuilder(RemoteAddress::class)
            ->disableOriginalConstructor()
            ->getMock();


        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->method('getResponse')->willReturn($this->responseMock);
        $this->resultJsonFactoryMock->method('create')->willReturn($this->resultJsonMock);

        $this->indexController = new Index(
            $this->contextMock,
            $this->notificationHelperMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->serializerMock,
            $this->configHelperMock,
            $this->ipAddressHelperMock,
            $this->rateLimiterHelperMock,
            $this->hmacSignatureMock,
            $this->notificationReceiverMock,
            $this->remoteAddressMock
        );
    }

    public function testLoadNotificationFromRequest()
    {
        $notificationMock = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->getMock();
        $notificationMock->expects($this->once())->method('setCreatedAt');
        $notificationMock->expects($this->once())->method('setUpdatedAt');
        $this->invokeMethod(
            $this->indexController,
            'loadNotificationFromRequest',
            [$notificationMock, []]
        );

    }
}
