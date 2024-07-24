<?php

namespace Adyen\Payment\Test\Unit\Model\Queue\Notification;

use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Queue\Notification\Publisher;
use Adyen\Payment\Model\ResourceModel\Notification as NotificationResourceModel;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\MessageQueue\PublisherInterface;

class PublisherTest extends AbstractAdyenTestCase
{
    /**
     * @return void
     * @throws Exception
     */
    public function testExecute(): void
    {
        $publisherMock = $this->createMock(PublisherInterface::class);
        $notificationResourceMock = $this->createMock(NotificationResourceModel::class);
        $notificationMock = $this->createMock(Notification::class);

        $notificationMock->expects($this->once())->method('setProcessing')->with(true);
        $notificationResourceMock->expects($this->once())->method('save')->with($notificationMock);
        $publisherMock->expects($this->once())->method('publish')->with(Publisher::TOPIC_NAME, $notificationMock);

        $publisher = new Publisher($publisherMock, $notificationResourceMock);
        $publisher->execute($notificationMock);
    }
}
