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

namespace Adyen\Payment\Cron;

use Adyen\AdyenException;
use Adyen\Payment\Api\AnalyticsEventRepositoryInterface;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Api\Data\AnalyticsEventStatusEnum;
use Adyen\Payment\Cron\Providers\AnalyticsEventProviderInterface;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Store\Model\StoreManagerInterface;

class SubmitAnalyticsEvents
{
    /**
     * @param AnalyticsEventProviderInterface[] $providers
     * @param CheckoutAnalytics $checkoutAnalyticsHelper
     * @param AnalyticsEventRepositoryInterface $analyticsEventRepository
     * @param Config $configHelper
     * @param StoreManagerInterface $storeManager
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly array $providers,
        private readonly CheckoutAnalytics $checkoutAnalyticsHelper,
        private readonly AnalyticsEventRepositoryInterface $analyticsEventRepository,
        private readonly Config $configHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenLogger $adyenLogger
    ) { }

    public function execute(): void
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isReliabilityDataCollectionEnabled = $this->configHelper->isReliabilityDataCollectionEnabled($storeId);

        if ($isReliabilityDataCollectionEnabled) {
            try {
                foreach ($this->providers as $provider) {
                    $analyticsEvents = array_values($provider->provide());
                    $context = $provider->getAnalyticsContext();
                    $numberOfEvents = count($analyticsEvents);

                    if ($numberOfEvents > 0) {
                        $analyticsEventsGroupedByVersion = $this->groupByVersion($analyticsEvents);

                        foreach ($analyticsEventsGroupedByVersion as $version => $items) {
                            $checkoutAttemptId = $this->getCheckoutAttemptId($version);
                            $maxNumber = CheckoutAnalytics::CONTEXT_MAX_ITEMS[$context];
                            $subsetOfEvents = [];

                            for ($i = 0; $i < count($items); $i++) {
                                $event = $items[$i];
                                $event->setStatus(AnalyticsEventStatusEnum::PROCESSING->value);
                                $event = $this->analyticsEventRepository->save($event);

                                $subsetOfEvents[] = $event;

                                if (count($subsetOfEvents) === $maxNumber || ((count($items) - ($i + 1)) === 0)) {
                                    $response = $this->checkoutAnalyticsHelper->sendAnalytics(
                                        $checkoutAttemptId,
                                        $subsetOfEvents,
                                        $context
                                    );

                                    foreach ($subsetOfEvents as $subsetOfEvent) {
                                        if (isset($response['error'])) {
                                            $subsetOfEvent->setErrorCount(
                                                $subsetOfEvent->getErrorCount() + 1
                                            );

                                            if ($subsetOfEvent->getErrorCount() ===
                                                AnalyticsEventInterface::MAX_ERROR_COUNT) {
                                                $subsetOfEvent->setScheduledProcessingTime();
                                                $subsetOfEvent->setStatus(AnalyticsEventStatusEnum::DONE->value);
                                            } else {
                                                $nextScheduledTime = date(
                                                    'Y-m-d H:i:s',
                                                    time() + (60 * 60 * $subsetOfEvent->getErrorCount() / 2)
                                                );
                                                $subsetOfEvent->setScheduledProcessingTime($nextScheduledTime);
                                                $subsetOfEvent->setStatus(AnalyticsEventStatusEnum::PENDING->value);
                                            }
                                        } else {
                                            $subsetOfEvent->setStatus(AnalyticsEventStatusEnum::DONE->value);
                                        }

                                        $this->analyticsEventRepository->save($subsetOfEvent);
                                    }

                                    $subsetOfEvents = [];
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $this->adyenLogger->error('Error while submitting analytics events: ' . $e->getMessage());
            }
        }
    }

    /**
     * @throws AdyenException
     */
    private function getCheckoutAttemptId($version): string
    {
        return $this->checkoutAnalyticsHelper->initiateCheckoutAttempt($version);
    }

    /**
     * This function groups analytics events by version
     *
     * @param array $analyticsEvents
     * @return array
     */
    private function groupByVersion(array $analyticsEvents): array
    {
        $groupedAnalyticsEvents = [];

        foreach ($analyticsEvents as $item) {
            $version = $item->getVersion();
            $groupedAnalyticsEvents[$version][] = $item;
        }

        return $groupedAnalyticsEvents;
    }
}
