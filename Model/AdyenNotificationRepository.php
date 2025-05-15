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

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\ObjectManagerInterface;

class AdyenNotificationRepository implements AdyenNotificationRepositoryInterface
{
    /**
     * @param ObjectManagerInterface $objectManager
     * @param string $resourceModel
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly string $resourceModel
    ) { }

    /**
     * Delete multiple entities with the given IDs
     *
     * @param array $entityIds
     * @return void
     */
    public function deleteByIds(array $entityIds): void
    {
        if (empty($entityIds)) {
            return;
        }

        $resource = $this->objectManager->get($this->resourceModel);
        $resource->deleteByIds($entityIds);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function save(NotificationInterface $entity): NotificationInterface
    {
        $this->resourceModel->save($entity);

        return $entity;
    }
}
