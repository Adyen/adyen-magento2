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

namespace Adyen\Payment\Test\Unit\Model\Sales;

use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use ReflectionClass;

class OrderRepositoryTest extends AbstractAdyenTestCase
{
    public function testGetOrderByQuoteId()
    {
        $quoteId = 1;

        $searchCriteriaBuilderMock = $this->createPartialMock(SearchCriteriaBuilder::class, ['create']);
        $searchCriteriaBuilderMock->method('create')
            ->willReturn($this->createMock(SearchCriteria::class));

        $filterBuilderMock = $this->createPartialMock(FilterBuilder::class, ['create']);
        $filterBuilderMock->method('create')
            ->willReturn($this->createMock(Filter::class));

        $filterGroupBuilderMock = $this->createPartialMock(FilterGroupBuilder::class, ['create']);
        $filterGroupBuilderMock->method('create')
            ->willReturn($this->createMock(FilterGroup::class));

        $sortOrderBuilderMock = $this->createPartialMock(SortOrderBuilder::class, ['create']);
        $sortOrderBuilderMock->method('create')
            ->willReturn($this->createMock(SortOrder::class));

        $orderRepository = $this->buildOrderRepositoryClass(
            $searchCriteriaBuilderMock,
            $filterBuilderMock,
            $filterGroupBuilderMock,
            $sortOrderBuilderMock
        );

        $order = $orderRepository->getOrderByQuoteId($quoteId);
        $this->assertInstanceOf(OrderInterface::class, $order);
    }

    public function buildOrderRepositoryClass(
        $searchCriteriaBuilderMock = null,
        $filterBuilderMock = null,
        $filterGroupBuilderMock = null,
        $sortOrderBuilderMock = null
    ): OrderRepository {
        if (is_null($searchCriteriaBuilderMock)) {
            $searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        }

        if (is_null($filterBuilderMock)) {
            $filterBuilderMock = $this->createMock(FilterBuilder::class);
        }

        if (is_null($filterGroupBuilderMock)) {
            $filterGroupBuilderMock = $this->createMock(FilterGroupBuilder::class);
        }

        if (is_null($sortOrderBuilderMock)) {
            $sortOrderBuilderMock = $this->createMock(SortOrderBuilder::class);
        }

        $orderRepositoryPartialMock = $this->getMockBuilder(OrderRepository::class)
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new ReflectionClass(OrderRepository::class);

        $searchCriteriaBuilderProperty = $reflection->getProperty('searchCriteriaBuilder');
        $searchCriteriaBuilderProperty->setAccessible(true);
        $searchCriteriaBuilderProperty->setValue($orderRepositoryPartialMock, $searchCriteriaBuilderMock);

        $filterBuilderProperty = $reflection->getProperty('filterBuilder');
        $filterBuilderProperty->setAccessible(true);
        $filterBuilderProperty->setValue($orderRepositoryPartialMock, $filterBuilderMock);

        $filterGroupBuilderProperty = $reflection->getProperty('filterGroupBuilder');
        $filterGroupBuilderProperty->setAccessible(true);
        $filterGroupBuilderProperty->setValue($orderRepositoryPartialMock, $filterGroupBuilderMock);

        $sortOrderBuilderProperty = $reflection->getProperty('sortOrderBuilder');
        $sortOrderBuilderProperty->setAccessible(true);
        $sortOrderBuilderProperty->setValue($orderRepositoryPartialMock, $sortOrderBuilderMock);

        $orderSearchResultMock = $this->createConfiguredMock(OrderSearchResultInterface::class, [
            'getItems' => [$this->createMock(OrderInterface::class)]
        ]);

        $orderRepositoryPartialMock->method('getList')
            ->willReturn($orderSearchResultMock);

        return $orderRepositoryPartialMock;
    }
}
