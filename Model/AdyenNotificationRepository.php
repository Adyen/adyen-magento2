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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;

class AdyenNotificationRepository implements AdyenNotificationRepositoryInterface
{
    /**
     * @param ObjectManagerInterface $objectManager
     * @param NotificationFactory $notificationFactory
     * @param string $resourceModel
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly NotificationFactory $notificationFactory,
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
        $resource = $this->objectManager->get($this->resourceModel);
        $resource->save($entity);

        return $entity;
    }

    /**
     * Returns the entity with the given `entity_id`
     *
     * @param int $entityId
     * @return NotificationInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): NotificationInterface
    {
        $resource = $this->objectManager->get($this->resourceModel);

        $entity = $this->notificationFactory->create();
        $resource->load($entity, $entityId, NotificationInterface::ENTITY_ID);

        if (!$entity->getEntityId()) {
            throw new NoSuchEntityException(
                __('Adyen notification with entity_id %1 does not exist.', $entityId)
            );
        }

        return $entity;
    }
}
