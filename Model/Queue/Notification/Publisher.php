<?php

namespace Adyen\Payment\Model\Queue\Notification;

use Adyen\Payment\Api\Data\NotificationInterface;
use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    private const TOPIC_NAME = "adyen.notification";

    /** @var PublisherInterface $publisher */
    private PublisherInterface $publisher;

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * @param NotificationInterface $notification
     * @return void
     */
    public function execute(NotificationInterface $notification): void
    {
        $this->publisher->publish(self::TOPIC_NAME, $notification);
    }
}
