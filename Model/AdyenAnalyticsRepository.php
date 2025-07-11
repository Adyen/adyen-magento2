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

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\AdyenAnalyticsRepositoryInterface;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent as AnalyticsEventResourceModel;
use Magento\Framework\Exception\NoSuchEntityException;

class AdyenAnalyticsRepository implements AdyenAnalyticsRepositoryInterface
{
    public function __construct(
        protected readonly AnalyticsEventResourceModel $resourceModel,
        protected readonly AnalyticsEventFactory $analyticsEventFactory
    ) { }

    public function save(AnalyticsEventInterface $analyticsEvent): AnalyticsEventInterface
    {
        $this->resourceModel->save($analyticsEvent);

        return $analyticsEvent;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $id): AnalyticsEventInterface
    {
        $analyticsEvent = $this->analyticsEventFactory->create();
        $this->resourceModel->load($analyticsEvent, $id);
        if (!$analyticsEvent->getId()) {
            throw new NoSuchEntityException(__('Unable to find analytics event with ID "%1"', $id));
        }
        return $analyticsEvent;
    }

    public function delete(AnalyticsEventInterface $analyticsEvent): void
    {
        $this->resourceModel->delete($analyticsEvent);
    }

    public function deleteById(int $id): void
    {
        $analytics = $this->getById($id);
        $this->delete($analytics);
    }
}
