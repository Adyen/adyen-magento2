<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\ObjectManagerInterface;

class AdyenNotificationRepository implements AdyenNotificationRepositoryInterface
{
    /**
     * @param SearchResultFactory $searchResultsFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessor $collectionProcessor
     * @param ObjectManagerInterface $objectManager
     * @param string $resourceModel
     */
    public function __construct(
        private readonly SearchResultFactory $searchResultsFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessor $collectionProcessor,
        private readonly ObjectManagerInterface $objectManager,
        private readonly string $resourceModel
    ) { }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $searchResult = $this->searchResultsFactory->create();
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    public function delete(NotificationInterface $entity): bool
    {
        $resource = $this->objectManager->get($this->resourceModel);
        $resource->delete($entity);

        return true;
    }
}
