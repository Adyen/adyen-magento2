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

namespace Adyen\Payment\Api\Repository;

use Adyen\Payment\Api\Data\NotificationInterface;

interface AdyenNotificationRepositoryInterface
{
    /**
     * Performs persist operations for a specified adyen_notification.
     *
     * @param NotificationInterface $entity adyen_notification entity
     * @return NotificationInterface
     */
    public function save(NotificationInterface $entity): NotificationInterface;

    /**
     * @param array $entityIds
     * @return void
     */
    public function deleteByIds(array $entityIds): void;

    /**
     * Returns the adyen_notification entity with the given `entity_id`.
     *
     * @param int $entityId adyen_notification entity_id
     * @return NotificationInterface
     */
    public function getById(int $entityId): NotificationInterface;
}
