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

namespace Adyen\Payment\Test\Unit\Model;

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
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Payment\Api\Data\PaymentAdditionalInfoInterfaceFactory;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Sales\Model\ResourceModel\Metadata;
use Magento\Tax\Api\OrderTaxManagementInterface;

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

        $orderRepository = $this->buildOrderRepositoryClass(
            $searchCriteriaBuilderMock,
            $filterBuilderMock,
            $filterGroupBuilderMock
        );

        $order = $orderRepository->getOrderByQuoteId($quoteId);
        $this->assertInstanceOf(OrderInterface::class, $order);
    }

    public function buildOrderRepositoryClass(
        $searchCriteriaBuilderMock = null,
        $filterBuilderMock = null,
        $filterGroupBuilderMock = null,
        $metadataMock = null,
        $searchResultFactoryMock = null,
        $collectionProcessorMock = null,
        $orderExtensionFactoryMock = null,
        $orderTaxManagementMock = null,
        $paymentAdditionalInfoFactoryMock = null,
        $serializerMock = null,
        $extensionAttributesJoinProcessorMock = null
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

        if (is_null($metadataMock)) {
            $metadataMock = $this->createMock(Metadata::class);
        }

        if (is_null($searchResultFactoryMock)) {
            $searchResultFactoryMock = $this->createGeneratedMock(SearchResultFactory::class);
        }

        if (is_null($collectionProcessorMock)) {
            $collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);
        }

        if (is_null($orderExtensionFactoryMock)) {
            $orderExtensionFactoryMock = $this->createGeneratedMock(OrderExtensionFactory::class);
        }

        if (is_null($orderTaxManagementMock)) {
            $orderTaxManagementMock = $this->createMock(OrderTaxManagementInterface::class);
        }

        if (is_null($paymentAdditionalInfoFactoryMock)) {
            $paymentAdditionalInfoFactoryMock = $this->createGeneratedMock(PaymentAdditionalInfoInterfaceFactory::class);
        }

        if (is_null($serializerMock)) {
            $serializerMock = $this->createMock(JsonSerializer::class);
        }

        if (is_null($extensionAttributesJoinProcessorMock)) {
            $extensionAttributesJoinProcessorMock = $this->createMock(JoinProcessorInterface::class);
        }

        $orderRepositoryPartialMock = $this->getMockBuilder(OrderRepository::class)
            ->setMethods(['getList'])
            ->setConstructorArgs([
                $searchCriteriaBuilderMock,
                $filterBuilderMock,
                $filterGroupBuilderMock,
                $metadataMock,
                $searchResultFactoryMock,
                $collectionProcessorMock,
                $orderExtensionFactoryMock,
                $orderTaxManagementMock,
                $paymentAdditionalInfoFactoryMock,
                $serializerMock,
                $extensionAttributesJoinProcessorMock
            ])
            ->getMock();

        $orderSearchResultMock = $this->createConfiguredMock(OrderSearchResultInterface::class, [
            'getItems' => [$this->createMock(OrderInterface::class)]
        ]);

        $orderRepositoryPartialMock->method('getList')
            ->willReturn($orderSearchResultMock);

        return $orderRepositoryPartialMock;
    }
}
