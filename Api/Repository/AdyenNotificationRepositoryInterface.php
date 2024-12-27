<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Repository;

use Adyen\Payment\Api\Data\NotificationInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;

interface AdyenNotificationRepositoryInterface
{
    /**
     * Retrieve Adyen Notification entities which match a specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     *
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Deletes a specified Adyen notification.
     *
     * @param NotificationInterface $entity The notification ID.
     * @return bool
     */
    public function delete(NotificationInterface $entity): bool;
}
