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
use Adyen\Payment\Api\Data\AnalyticsEventInterfaceFactory;
use Adyen\Payment\Api\Data\AnalyticsEventStatusEnum;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Cron\Providers\AnalyticsEventProviderInterface;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Util\Uuid;
use Exception;

class SubmitAnalyticsEvents
{
    private ?string $checkoutAttemptId = null;

    /**
     * @param AnalyticsEventProviderInterface[] $providers
     * @param CheckoutAnalytics $checkoutAnalyticsHelper
     * @param AnalyticsEventInterfaceFactory $analyticsEventFactory
     * @param AnalyticsEventRepositoryInterface $analyticsEventRepository
     */
    public function __construct(
        protected readonly array $providers,
        protected readonly CheckoutAnalytics $checkoutAnalyticsHelper,
        protected readonly AnalyticsEventInterfaceFactory $analyticsEventFactory,
        protected readonly AnalyticsEventRepositoryInterface $analyticsEventRepository,
    ) { }

    public function execute(): void
    {
        $this->createTestData();

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
            //TODO:: Handle unexpected cases
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

    // TODO:: Remove this test method before merging the PR!
    private function createTestData()
    {
        $counter = 100;

        for ($i = 0; $i < $counter; $i++) {
            /** @var AnalyticsEventInterface $analyticsEvent */
            $analyticsEvent = $this->analyticsEventFactory->create();

            $analyticsEvent->setRelationId('MOCK_RELATION_ID');
            $analyticsEvent->setUuid(Uuid::generateV4());
            $analyticsEvent->setType(AnalyticsEventTypeEnum::EXPECTED_START->value);
            $analyticsEvent->setTopic('MOCK_TOPIC');
            $analyticsEvent->setStatus(AnalyticsEventStatusEnum::PENDING->value);

            $this->analyticsEventRepository->save($analyticsEvent);
        }
    }
}
