<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\CollectionFactory;
use Exception;

class AnalyticsEventsCleanUp
{
    /**
     * @param CollectionFactory $analyticsEventCollectionFactory
     * @param AnalyticsEvent $analyticsEventResourceModel
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly CollectionFactory $analyticsEventCollectionFactory,
        private readonly AnalyticsEvent $analyticsEventResourceModel,
        private readonly AdyenLogger $adyenLogger
    ) {}

    /**
     * This method is executed by the cron job `adyen_payment_clean_up_analytics_events` and deletes
     * the analytics events that are older than 45 days OR are in the done state.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $collection = $this->analyticsEventCollectionFactory->create()->analyticsEventsToCleanUp();

            if ($collection->getSize() > 0) {
                $ids = $collection->getColumnValues(AnalyticsEventInterface::ENTITY_ID);
                $this->analyticsEventResourceModel->deleteByIds($ids);
            }
        } catch (Exception $e) {
            $this->adyenLogger->error(
                sprintf("An error occurred while cleaning up the analytics events: %s", $e->getMessage())
            );
        }
    }
}
