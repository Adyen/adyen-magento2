<?php

namespace Adyen\Payment\Model\Queue\Notification;

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Model\ResourceModel\Notification;
use Exception;
use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    public const TOPIC_NAME = "adyen.notification";

    /** @var PublisherInterface $publisher */
    private $publisher;

    /** @var Notification $notificationResource */
    private $notificationResource;

    /**
     * @param PublisherInterface $publisher
     * @param Notification $notificationResource
     */
    public function __construct(
        PublisherInterface $publisher,
        Notification $notificationResource
    ) {
        $this->publisher = $publisher;
        $this->notificationResource = $notificationResource;
    }

    /**
     * @param NotificationInterface $notification
     * @return void
     * @throws Exception
     */
    public function execute(NotificationInterface $notification): void
    {
        // Set processing=true to skip adding duplicate queue entries
        $notification->setProcessing(true);
        $this->notificationResource->save($notification);

        $this->publisher->publish(self::TOPIC_NAME, $notification);
    }
}
