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

namespace Adyen\Payment\Test\Unit\Model\Sales;

use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Metadata;
use Magento\Tax\Api\Data\OrderTaxDetailsInterface;
use Magento\Tax\Api\OrderTaxManagementInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Framework\Exception\NoSuchEntityException;

class OrderRepositoryTest extends AbstractAdyenTestCase
{
    protected OrderRepository $orderRepository;
    protected SearchCriteriaBuilder|MockObject $searchCriteriaBuilder;
    protected FilterBuilder|MockObject $filterBuilder;
    protected FilterGroupBuilder|MockObject $filterGroupBuilder;
    protected SortOrderBuilder|MockObject $sortOrderBuilder;
    protected Metadata|MockObject $metadata;
    protected SearchResultFactory|MockObject $searchResultFactory;
    protected Collection|MockObject $searchResult;

    protected function setUp(): void
    {
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->filterBuilder = $this->createMock(FilterBuilder::class);
        $this->filterGroupBuilder = $this->createMock(FilterGroupBuilder::class);
        $this->sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $this->metadata = $this->createMock(Metadata::class);

        $this->searchResult = $this->createMock(Collection::class);
        $this->searchResultFactory = $this->createMock(SearchResultFactory::class);
        $this->searchResultFactory->method('create')->willReturn($this->searchResult);

        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        ObjectManager::setInstance($objectManagerMock);

        $extensionAttributesJoinProcessor = $this->createMock(JoinProcessorInterface::class);
        $collectionProcessor = $this->createMock(CollectionProcessorInterface::class);

        $orderTaxDetails = $this->createMock(OrderTaxDetailsInterface::class);

        $orderTaxManagement = $this->createMock(OrderTaxManagementInterface::class);
        $orderTaxManagement->method('getOrderTaxDetails')->willReturn($orderTaxDetails);

        $this->orderRepository = new OrderRepository(
            $this->searchCriteriaBuilder,
            $this->filterBuilder,
            $this->filterGroupBuilder,
            $this->sortOrderBuilder,
            $this->metadata,
            $this->searchResultFactory,
            $collectionProcessor,
            $orderTaxManagement,
            null,
            null,
            $extensionAttributesJoinProcessor
        );
    }

    public function testGetOrderByQuoteId()
    {
        $quoteId = 1;

        $filter = $this->createMock(Filter::class);
        $filterGroup = $this->createMock(FilterGroup::class);
        $sortOrder = $this->createMock(SortOrder::class);
        $searchCriteria = $this->createMock(SearchCriteria::class);

        $orderExtensionMock = $this->createMock(\Magento\Sales\Api\Data\OrderExtensionInterface::class);
        $orderExtensionMock->method('getShippingAssignments')->willReturn(true);

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getExtensionAttributes')->willReturn($orderExtensionMock);

        $this->searchResult->method('getItems')->willReturn([$orderMock]);

        $this->filterBuilder->method('setField')->with('quote_id')->willReturnSelf();
        $this->filterBuilder->method('setConditionType')->with('eq')->willReturnSelf();
        $this->filterBuilder->method('setValue')->with($quoteId)->willReturnSelf();
        $this->filterBuilder->method('create')->willReturn($filter);

        $this->filterGroupBuilder->method('setFilters')->with([$filter])->willReturnSelf();
        $this->filterGroupBuilder->method('create')->willReturn($filterGroup);

        $this->sortOrderBuilder->method('setField')->with('entity_id')->willReturnSelf();
        $this->sortOrderBuilder->method('setDescendingDirection')->willReturnSelf();
        $this->sortOrderBuilder->method('create')->willReturn($sortOrder);

        $this->searchCriteriaBuilder->method('setFilterGroups')->with([$filterGroup])->willReturnSelf();
        $this->searchCriteriaBuilder->method('setSortOrders')->with([$sortOrder])->willReturnSelf();
        $this->searchCriteriaBuilder->method('setPageSize')->with(1)->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);
        $this->assertInstanceOf(OrderInterface::class, $order);
    }

    public function testGetByIncrementIdSuccess()
    {
        $incrementId = '000000001';
        $entityId = 123;

        $orderExtensionMock = $this->createMock(\Magento\Sales\Api\Data\OrderExtensionInterface::class);
        $orderExtensionMock->method('getShippingAssignments')->willReturn(true);

        $orderModelMock = $this->createMock(Order::class);
        $orderModelMock->method('getEntityId')->willReturn($entityId);
        $orderModelMock->method('loadByIncrementId')->with($incrementId)->willReturnSelf();
        $orderModelMock->method('load')->with($entityId)->willReturnSelf();
        $orderModelMock->method('getExtensionAttributes')->willReturn($orderExtensionMock);

        $this->metadata->method('getNewInstance')->willReturn($orderModelMock);

        $result = $this->orderRepository->getByIncrementId($incrementId);

        $this->assertInstanceOf(OrderInterface::class, $result);
    }

    public function testGetByIncrementIdThrowsExceptionWhenOrderNotFound()
    {
        $incrementId = '000000999';

        $orderModelMock = $this->createMock(Order::class);
        $orderModelMock->method('getEntityId')->willReturn(null);

        $this->metadata->method('getNewInstance')->willReturn($orderModelMock);
        $orderModelMock->method('loadByIncrementId')->with($incrementId)->willReturnSelf();

        $this->expectException(NoSuchEntityException::class);

        $this->orderRepository->getByIncrementId($incrementId);
    }
}
