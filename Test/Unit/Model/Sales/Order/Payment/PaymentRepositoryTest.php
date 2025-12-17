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

namespace Adyen\Payment\Test\Unit\Model\Sales\Order\Payment;

use Adyen\Payment\Model\Sales\Order\Payment\PaymentRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderPaymentSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Sales\Model\ResourceModel\Metadata;
use Magento\Sales\Model\ResourceModel\Order\Payment\Collection as MagentoPaymentCollection;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentRepositoryTest extends AbstractAdyenTestCase
{
    protected ?PaymentRepository $paymentRepository;
    protected SearchCriteriaBuilder|MockObject $searchCriteriaBuilderMock;
    protected FilterBuilder|MockObject $filterBuilderMock;
    protected FilterGroupBuilder|MockObject $filterGroupBuilderMock;
    protected Metadata|MockObject $metaDataMock;
    protected SearchResultFactory|MockObject $searchResultFactoryMock;
    protected CollectionProcessorInterface|MockObject $collectionProcessorMock;
    protected MagentoPaymentCollection|MockObject $searchResultMock;

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        // Constructor argument mocks
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->filterBuilderMock = $this->createMock(FilterBuilder::class);
        $this->filterGroupBuilderMock = $this->createMock(FilterGroupBuilder::class);
        $this->metaDataMock = $this->createMock(Metadata::class);
        $this->searchResultFactoryMock = $this->createMock(SearchResultFactory::class);
        $this->collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);

        // Method result mocks
        $this->searchResultMock = $this->createMock(MagentoPaymentCollection::class);

        $this->paymentRepository = new PaymentRepository(
            $this->searchCriteriaBuilderMock,
            $this->filterBuilderMock,
            $this->filterGroupBuilderMock,
            $this->metaDataMock,
            $this->searchResultFactoryMock,
            $this->collectionProcessorMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->paymentRepository = null;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetPaymentByCcTransIdEmptyResult()
    {
        $ccTransId = 'MOCK_PSPRERENCE';
        $this->prepareMethodMocks($ccTransId);

        $this->searchResultMock->method('getSize')->willReturn(0);

        $this->assertNull($this->paymentRepository->getPaymentByCcTransId($ccTransId));
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testGetPaymentByCcTransIdValidResult()
    {
        $ccTransId = 'MOCK_PSPRERENCE';
        $this->prepareMethodMocks($ccTransId);

        $paymentMock = $this->createMock(OrderPaymentInterface::class);

        $this->searchResultMock->method('getSize')->willReturn(1);
        $this->searchResultMock->method('getFirstItem')->willReturn($paymentMock);

        $result = $this->paymentRepository->getPaymentByCcTransId($ccTransId);

        $this->assertInstanceOf(OrderPaymentInterface::class, $result);
    }

    /**
     * @param string $ccTransId
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    private function prepareMethodMocks(string $ccTransId): void
    {
        $filterMock = $this->createMock(Filter::class);

        $this->filterBuilderMock->method('setField')->with('cc_trans_id')->willReturnSelf();
        $this->filterBuilderMock->method('setConditionType')->with('eq')->willReturnSelf();
        $this->filterBuilderMock->method('setValue')->with($ccTransId)->willReturnSelf();
        $this->filterBuilderMock->method('create')->willReturn($filterMock);

        $filterGroupMock = $this->createMock(FilterGroup::class);
        $this->filterGroupBuilderMock->method('setFilters')->with([$filterMock])->willReturnSelf();
        $this->filterGroupBuilderMock->method('create')->willReturn($filterGroupMock);

        $searchCriteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilderMock->method('setFilterGroups')
            ->with([$filterGroupMock])
            ->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('setPageSize')
            ->with(1)
            ->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('create')->willReturn($searchCriteriaMock);

        $this->searchResultFactoryMock->method('create')->willReturn($this->searchResultMock);
        $this->collectionProcessorMock->method('process')->with($searchCriteriaMock, $this->searchResultMock);
    }
}
