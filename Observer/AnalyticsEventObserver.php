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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Helper\Util\Uuid;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Adyen\Payment\Api\AnalyticsEventRepositoryInterface;
use Adyen\Payment\Model\AnalyticsEventFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class AnalyticsEventObserver implements ObserverInterface
{
    /**
     * @param AnalyticsEventRepositoryInterface $adyenAnalyticsRepository
     * @param AnalyticsEventFactory $analyticsEventFactory
     * @param PlatformInfo $platformInfo
     * @param Config $configHelper
     * @param StoreManagerInterface $storeManager
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AnalyticsEventRepositoryInterface $adyenAnalyticsRepository,
        private readonly AnalyticsEventFactory $analyticsEventFactory,
        private readonly PlatformInfo $platformInfo,
        private readonly Config $configHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param Observer $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isReliabilityDataCollectionEnabled = $this->configHelper->isReliabilityDataCollectionEnabled($storeId);

        if ($isReliabilityDataCollectionEnabled) {
            try {
                $eventData = $observer->getEvent()->getData('data');

                $analyticsEvent = $this->analyticsEventFactory->create();
                $analyticsEvent->setUuid(Uuid::generateV4());
                $analyticsEvent->setRelationId($eventData['relationId']);
                $analyticsEvent->setType($eventData['type']);
                $analyticsEvent->setTopic($eventData['topic']);
                $analyticsEvent->setVersion($this->platformInfo->getModuleVersion());
                if (isset($eventData['message'])) {
                    $analyticsEvent->setMessage($eventData['message']);
                }

                $this->adyenAnalyticsRepository->save($analyticsEvent);
            } catch (Exception $e) {
                $this->adyenLogger->error('Error processing payment_method_adyen_analytics event: ' . $e->getMessage());
            }
        }
    }
}
