<?php

namespace Adyen\Payment\Test\Unit\Model\Queue\Notification;

use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Queue\Notification\Consumer;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;

class ConsumerTest extends AbstractAdyenTestCase
{
    /** @var Webhook|MockObject $webhookMock */
    private $webhookMock;

    /** @var AdyenLogger|MockObject $adyenLoggerMock */
    private $adyenLoggerMock;

    /** @var Notification|MockObject $notificationMock */
    private $notificationMock;

    /** @var Consumer $consumer */
    private $consumer;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->webhookMock = $this->createMock(Webhook::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->notificationMock = $this->createMock(Notification::class);
        $this->consumer = new Consumer($this->webhookMock, $this->adyenLoggerMock);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testExecute(): void
    {
        $this->webhookMock->expects($this->once())
            ->method('processNotification')
            ->with($this->notificationMock)
            ->willReturn(true);

        $this->assertTrue($this->consumer->execute($this->notificationMock));
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testExecuteThrowsException(): void
    {
        $this->webhookMock->expects($this->once())
            ->method('processNotification')
            ->with($this->notificationMock)
            ->willThrowException(new Exception());
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenWarning');

        $this->expectException(Exception::class);
        $this->consumer->execute($this->notificationMock);
    }
}
