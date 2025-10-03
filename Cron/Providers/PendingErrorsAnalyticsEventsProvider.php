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

namespace Adyen\Payment\Cron\Providers;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\Collection as AnalyticsEventCollection;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\CollectionFactory as AnalyticsEventCollectionFactory;

class PendingErrorsAnalyticsEventsProvider implements AnalyticsEventProviderInterface
{
    const PROVIDER_NAME = 'Pending analytics events for `errors` context';

    public function __construct(
        private readonly AnalyticsEventCollectionFactory $analyticsEventCollectionFactory
    ) {}

    /**
     * @return AnalyticsEventInterface[]
     * @throws AdyenException
     */
    public function provide(): array
    {
        $analyticsEventCollection = $this->analyticsEventCollectionFactory->create();

        /** @var AnalyticsEventCollection $analyticsEventCollection */
        $analyticsEventCollection = $analyticsEventCollection->pendingAnalyticsEvents([
            AnalyticsEventTypeEnum::UNEXPECTED_END
        ]);

        return $analyticsEventCollection->getItems();
    }

    /**
     * @return string
     */
    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }

    /**
     * @return string
     */
    public function getAnalyticsContext(): string
    {
        return CheckoutAnalytics::CONTEXT_TYPE_ERRORS;
    }
}
