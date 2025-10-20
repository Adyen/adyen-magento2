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
use Adyen\Payment\Logger\AdyenLogger;
use Exception;

class SubmitAnalyticsEvents
{
    private ?string $checkoutAttemptId = null;

    /**
     * @param AnalyticsEventProviderInterface[] $providers
     * @param CheckoutAnalytics $checkoutAnalyticsHelper
     * @param AnalyticsEventRepositoryInterface $analyticsEventRepository
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly array $providers,
        private readonly CheckoutAnalytics $checkoutAnalyticsHelper,
        private readonly AnalyticsEventRepositoryInterface $analyticsEventRepository,
        private readonly AdyenLogger $adyenLogger
    ) { }

    public function execute(): void
    {
        try {
            foreach ($this->providers as $provider) {
                $analyticsEvents = array_values($provider->provide());
                $context = $provider->getAnalyticsContext();
                $numberOfEvents = count($analyticsEvents);

                if ($numberOfEvents > 0) {
                    $checkoutAttemptId = $this->getCheckoutAttemptId();
                    $maxNumber = CheckoutAnalytics::CONTEXT_MAX_ITEMS[$context];
                    $subsetOfEvents = [];

                    for ($i = 0; $i < count($analyticsEvents); $i++) {
                        $event = $analyticsEvents[$i];
                        $event->setStatus(AnalyticsEventStatusEnum::PROCESSING->value);
                        $event = $this->analyticsEventRepository->save($event);

                        $subsetOfEvents[] = $event;

                        if (count($subsetOfEvents) === $maxNumber || (($numberOfEvents - $i + 1) < $maxNumber)) {
                            $response = $this->checkoutAnalyticsHelper->sendAnalytics(
                                $checkoutAttemptId,
                                $subsetOfEvents,
                                $context
                            );

                            foreach ($subsetOfEvents as $subsetOfEvent) {
                                if (isset($response['error'])) {
                                    $subsetOfEvent->setErrorCount($subsetOfEvent->getErrorCount() + 1);

                                    if ($subsetOfEvent->getErrorCount() === AnalyticsEventInterface::MAX_ERROR_COUNT) {
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
        } catch (Exception $e) {
            $this->adyenLogger->error('Error while submitting analytics events: ' . $e->getMessage());
        }
    }

    /**
     * @throws AdyenException
     */
    private function getCheckoutAttemptId(): string
    {
        if (empty($this->checkoutAttemptId)) {
            $this->checkoutAttemptId = $this->checkoutAnalyticsHelper->initiateCheckoutAttempt();
        }

        return $this->checkoutAttemptId;
    }
}
