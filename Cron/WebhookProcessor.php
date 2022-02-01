<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
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

use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;

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
     * Cron constructor.
     *
     * @param AdyenLogger $adyenLogger
     * @param CollectionFactory $notificationCollectionFactory
     * @param Webhook $webhookHelper
     */
    public function __construct(
        AdyenLogger $adyenLogger,
        CollectionFactory $notificationCollectionFactory,
        Webhook $webhookHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->notificationCollectionFactory = $notificationCollectionFactory;
        $this->webhookHelper = $webhookHelper;
    }

    /**
     * Run the webhook processor
     *
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        try {
            $this->doProcessWebhook();
        } catch (\Exception $e) {
            $this->adyenLogger->addAdyenNotificationCronjob($e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * @throws \Exception
     */
    public function doProcessWebhook()
    {
        // Fetch notifications collection
        $notifications = $this->notificationCollectionFactory->create();
        $notifications->notificationsToProcessFilter();

        // Loop through and process notifications.
        $count = 0;
        /** @var Notification[] $notifications */
        foreach ($notifications as $notification) {
            // ignore duplicate notification
            if ($notification->isDuplicate(
                    $notification->getPspreference(),
                    $notification->getEventCode(),
                    $notification->getSuccess(),
                    $notification->getOriginalReference(),
                    true
                )
            ) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    "This is a duplicate notification and will be ignored",
                    $notification->toArray(['entity_id', 'pspreference', 'event_code', 'success', 'original_reference'])
                );
                continue;
            }

            // Skip notifications that should be delayed
            if ($this->webhookHelper->shouldSkipProcessingNotification($notification)) {
                continue;
            }

            if($this->webhookHelper->processNotification($notification)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf("Cronjob updated %s notification(s)", $count));
        }
    }

}
