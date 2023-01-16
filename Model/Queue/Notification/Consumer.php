<?php

namespace Adyen\Payment\Model\Queue\Notification;

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;

class Consumer
{
    public const TOPIC_NAME = "adyen.notification";

    /** @var Webhook $webhookHelper */
    private $webhookHelper;

    /** @var AdyenLogger $adyenLogger */
    private $adyenLogger;

    /**
     * @param Webhook $webhookHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Webhook $webhookHelper,
        AdyenLogger $adyenLogger,
    ) {
        $this->webhookHelper = $webhookHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param NotificationInterface $notification
     * @return bool
     * @throws Exception
     */
    public function execute(NotificationInterface $notification): bool
    {
        try {
            return $this->webhookHelper->processNotification($notification);
        } catch (Exception $e) {
            $this->adyenLogger->addAdyenWarning($e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }
}
