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
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Payment\Api\Data\PaymentAdditionalInfoInterfaceFactory;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Sales\Model\OrderRepository as SalesOrderRepository;
use Magento\Sales\Model\ResourceModel\Metadata;
use Magento\Tax\Api\OrderTaxManagementInterface;

class OrderRepository extends SalesOrderRepository
{
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private FilterBuilder $filterBuilder;
    private FilterGroupBuilder $filterGroupBuilder;

    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        Metadata $metadata,
        SearchResultFactory $searchResultFactory,
        CollectionProcessorInterface $collectionProcessor = null,
        OrderExtensionFactory $orderExtensionFactory = null,
        OrderTaxManagementInterface $orderTaxManagement = null,
        PaymentAdditionalInfoInterfaceFactory $paymentAdditionalInfoFactory = null,
        JsonSerializer $serializer = null,
        JoinProcessorInterface $extensionAttributesJoinProcessor = null
    ) {
        parent::__construct(
            $metadata,
            $searchResultFactory,
            $collectionProcessor,
            $orderExtensionFactory,
            $orderTaxManagement,
            $paymentAdditionalInfoFactory,
            $serializer,
            $extensionAttributesJoinProcessor
        );

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
    }

    public function getOrderByQuoteId(int $quoteId): OrderInterface|false
    {
        $quoteIdFilter = $this->filterBuilder->setField('quote_id')
            ->setConditionType('eq')
            ->setValue($quoteId)
            ->create();

        $quoteIdFilterGroup = $this->filterGroupBuilder->setFilters([$quoteIdFilter])->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$quoteIdFilterGroup])
            ->create();

        $orders = $this->getList($searchCriteria)->getItems();

        /** @var OrderInterface $order */
        return reset($orders);
    }
}
