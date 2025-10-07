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

use Adyen\Payment\Helper\Util\Uuid;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Adyen\Payment\Api\AnalyticsEventRepositoryInterface;
use Adyen\Payment\Model\AnalyticsEventFactory;

class AnalyticsEventObserver implements ObserverInterface
{
    public function __construct(
        private readonly AnalyticsEventRepositoryInterface $adyenAnalyticsRepository,
        private readonly AnalyticsEventFactory $analyticsEventFactory,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $eventData = $observer->getEvent()->getData('data');

            $analyticsEvent = $this->analyticsEventFactory->create();
            $analyticsEvent->setUuid(Uuid::generateV4());
            $analyticsEvent->setRelationId($eventData['relationId']);
            $analyticsEvent->setType($eventData['type']);
            $analyticsEvent->setTopic($eventData['topic']);
            if (isset($eventData['message'])) {
                $analyticsEvent->setMessage($eventData['message']);
            }

            $this->adyenAnalyticsRepository->save($analyticsEvent);
        } catch (Exception $e) {
            $this->adyenLogger->error('Error processing payment_method_adyen_analytics event: ' . $e->getMessage());
        }
    }
}
