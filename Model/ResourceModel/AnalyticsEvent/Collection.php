<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\AnalyticsEvent;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Api\Data\AnalyticsEventStatusEnum;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Cron\Providers\AnalyticsEventProviderInterface;
use Adyen\Payment\Model\AnalyticsEvent as AnalyticsEventModel;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent as AnalyticsEventResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    public function _construct()
    {
        $this->_init(
            AnalyticsEventModel::class,
            AnalyticsEventResourceModel::class
        );
    }

    /**
     * @param AnalyticsEventTypeEnum[] $analyticsEventTypes
     * @return $this
     * @throws AdyenException
     */
    public function pendingAnalyticsEvents(array $analyticsEventTypes): Collection
    {
        if (empty($analyticsEventTypes)) {
            throw new AdyenException(__('Empty required analyticsEventTypes argument!'));
        }

        $typeValues = [];
        foreach ($analyticsEventTypes as $type) {
            if ($type instanceof AnalyticsEventTypeEnum) {
                $typeValues[] = $type->value;
            }
        }

        if (empty($typeValues)) {
            throw new AdyenException(__('Invalid analyticsEventTypes argument!'));
        }

        $this->addFieldToFilter(AnalyticsEventInterface::TYPE, ['in' => $typeValues]);

        $this->addFieldToFilter(
            AnalyticsEventInterface::STATUS,
            AnalyticsEventStatusEnum::PENDING->value
        );

        $this->addFieldToFilter(AnalyticsEventInterface::ERROR_COUNT, [
            'lt' => AnalyticsEventInterface::MAX_ERROR_COUNT]);

        $this->addFieldToFilter(AnalyticsEventInterface::SCHEDULED_PROCESSING_TIME, [
            'lt' => date('Y-m-d H:i:s')]);

        $this->setPageSize(AnalyticsEventProviderInterface::BATCH_SIZE);

        return $this;
    }

    /**
     * This method returns the analytics events that are older than 45 days OR are in the done state.
     *
     * @return $this
     */
    public function analyticsEventsToCleanUp(): Collection
    {
        $daysLimit = date('Y-m-d H:i:s', strtotime('-45 days'));

        $this->addFieldToFilter(
            [
                AnalyticsEventInterface::STATUS,
                AnalyticsEventInterface::CREATED_AT
            ],
            [
                ['eq' => AnalyticsEventStatusEnum::DONE->value],
                ['lt' => $daysLimit]
            ]
        );

        $this->setPageSize(AnalyticsEventProviderInterface::CLEAN_UP_BATCH_SIZE);

        return $this;
    }
}
