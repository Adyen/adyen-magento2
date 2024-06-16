<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Queue\Notification\Publisher;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Exception;

class WebhookProcessor
{
    /**
     * Logging instance
     *
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var CollectionFactory
     */
    private $notificationCollectionFactory;

    /**
     * @var Webhook
     */
    private $webhookHelper;

    /**
     * @var Config $configHelper
     */
    private $configHelper;

    /**
     * @var Publisher $notificationPublisher
     */
    private $notificationPublisher;

    /**
     * Cron constructor.
     *
     * @param AdyenLogger $adyenLogger
     * @param CollectionFactory $notificationCollectionFactory
     * @param Webhook $webhookHelper
     * @param Config $configHelper
     * @param Publisher $notificationPublisher
     */
    public function __construct(
        AdyenLogger $adyenLogger,
        CollectionFactory $notificationCollectionFactory,
        Webhook $webhookHelper,
        Config $configHelper,
        Publisher $notificationPublisher
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->notificationCollectionFactory = $notificationCollectionFactory;
        $this->webhookHelper = $webhookHelper;
        $this->configHelper = $configHelper;
        $this->notificationPublisher = $notificationPublisher;
    }

    /**
     * Run the webhook processor
     *
     * @return void
     * @throws Exception
     */
    public function execute()
    {
        try {
            $this->doProcessWebhook();
        } catch (Exception $e) {
            $this->adyenLogger->addAdyenWarning($e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function doProcessWebhook()
    {
        // Fetch notifications collection
        $notifications = $this->notificationCollectionFactory->create();
        $notifications->notificationsToProcessFilter();

        // Loop through and process notifications.
        $count = 0;
        $queued = 0;

        /** @var Notification[] $notifications */
        foreach ($notifications as $notification) {
            // ignore duplicate notification
            if ($notification->isDuplicate(true)) {
                $this->adyenLogger->addAdyenNotification(
                    "This is a duplicate notification and will be ignored",
                    $notification->toArray(['entity_id', 'pspreference', 'event_code', 'success', 'original_reference'])
                );
                continue;
            }

            // Skip notifications that should be delayed
            if ($notification->shouldSkipProcessing()) {
                $this->adyenLogger->addAdyenNotification(
                    sprintf(
                        '%s notification (entity_id: %s) is skipped! Wait 10 minute before processing.',
                        $notification->getEventCode(),
                        $notification->getEntityId()
                    ),
                    [
                        'pspReference' => $notification->getPspreference(),
                        'merchantReference' => $notification->getMerchantReference()
                    ]
                );
                continue;
            }

            if ($this->configHelper->useQueueProcessor()) {
                $this->notificationPublisher->execute($notification);
                $queued++;
                $count++;
            } elseif ($this->webhookHelper->processNotification($notification)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->adyenLogger->addAdyenNotification(sprintf(
                "Cronjob updated %s notification(s)", $count
            ), [
                'pspReference' => $notification->getPspreference(),
                'merchantReference' => $notification->getMerchantReference(),
                'queued' => $queued
            ]);
        }
    }

}
