<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Adyen\Payment\Api\AdyenAnalyticsRepositoryInterface;
use Adyen\Payment\Model\AnalyticsEventFactory;
use Psr\Log\LoggerInterface;

class ProcessAnalyticsEvent implements ObserverInterface
{
    public function __construct(
        protected readonly AdyenAnalyticsRepositoryInterface $adyenAnalyticsRepository,
        protected readonly AnalyticsEventFactory $analyticsEventFactory,
        protected readonly LoggerInterface $logger
    ) { }

    public function execute(Observer $observer)
    {
        try {
            // Get the event data
            $eventData = $observer->getEvent()->getData('data');

            // Log the event data for debugging (optional)
            $this->logger->info('Received event data for payment_method_adyen_analytics: ', $eventData);

            // Create a new instance of the AdyenAnalytics model
            $analytics = $this->analyticsEventFactory->create();

            // Populate the model with event data
            $analytics->setRelationId($eventData['relationId']);
            $analytics->setType($eventData['type']);
            $analytics->setTopic($eventData['topic']);
            $analytics->setMessage($eventData['message']);

            // Save the event data to the database
            $this->adyenAnalyticsRepository->save($analytics);

        } catch (\Exception $e) {
            // Log any exceptions
            $this->logger->error('Error processing payment_method_adyen_analytics event: ' . $e->getMessage());
        }
    }
}
