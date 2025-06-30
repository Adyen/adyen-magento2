<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Sales;

use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Payment\Api\Data\PaymentAdditionalInfoInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Sales\Model\OrderRepository as SalesOrderRepository;
use Magento\Sales\Model\ResourceModel\Metadata;
use Magento\Tax\Api\OrderTaxManagementInterface;
use Magento\Sales\Model\Order\ShippingAssignmentBuilder;

class OrderRepository extends SalesOrderRepository
{
    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param Metadata $metadata
     * @param SearchResultFactory $searchResultFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     * @param OrderTaxManagementInterface|null $orderTaxManagement
     * @param PaymentAdditionalInfoInterfaceFactory|null $paymentAdditionalInfoFactory
     * @param JsonSerializer|null $serializer
     * @param JoinProcessorInterface|null $extensionAttributesJoinProcessor
     * @param ShippingAssignmentBuilder|null $shippingAssignmentBuilder
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        Metadata $metadata,
        SearchResultFactory $searchResultFactory,
        ?CollectionProcessorInterface $collectionProcessor = null,
        ?OrderTaxManagementInterface $orderTaxManagement = null,
        ?PaymentAdditionalInfoInterfaceFactory $paymentAdditionalInfoFactory = null,
        ?JsonSerializer $serializer = null,
        ?JoinProcessorInterface $extensionAttributesJoinProcessor = null,
        ?ShippingAssignmentBuilder $shippingAssignmentBuilder = null
    ) {
        parent::__construct(
            $metadata,
            $searchResultFactory,
            $collectionProcessor,
            $orderTaxManagement,
            $paymentAdditionalInfoFactory,
            $serializer,
            $extensionAttributesJoinProcessor,
            $shippingAssignmentBuilder
        );
    }

    public function getOrderByQuoteId(int $quoteId): OrderInterface|false
    {
        $quoteIdFilter = $this->filterBuilder->setField('quote_id')
            ->setConditionType('eq')
            ->setValue($quoteId)
            ->create();

        $quoteIdFilterGroup = $this->filterGroupBuilder->setFilters([$quoteIdFilter])->create();
        $sortOrder = $this->sortOrderBuilder->setField('entity_id')
            ->setDescendingDirection()
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$quoteIdFilterGroup])
            ->setSortOrders([$sortOrder])
            ->setPageSize(1)
            ->create();

        $orders = $this->getList($searchCriteria)->getItems();

        /** @var OrderInterface $order */
        return reset($orders);
    }
}
