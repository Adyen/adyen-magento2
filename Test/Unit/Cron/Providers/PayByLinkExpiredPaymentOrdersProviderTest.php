<?php

use Adyen\Payment\Cron\Providers\PayByLinkExpiredPaymentOrdersProvider;
use Magento\Framework\Api\AbstractSimpleObject;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\ObjectFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class PayByLinkExpiredPaymentOrdersProviderTest extends TestCase
{
    protected PayByLinkExpiredPaymentOrdersProvider|MockObject $payByLinkExpiredPaymentOrdersProvider;
    protected OrderRepositoryInterface|MockObject $orderRepository;
    protected OrderPaymentRepositoryInterface|MockObject $orderPaymentRepository;

    public function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderPaymentRepository = $this->createMock(OrderPaymentRepositoryInterface::class);
        $objectFactory = $this->createMock(ObjectFactory::class);
        $filterBuilder = new FilterBuilder($objectFactory);
        $filterGroupBuilder = new FilterGroupBuilder($objectFactory, $filterBuilder);
        $searchCriteriaBuilder = new SearchCriteriaBuilder($objectFactory, $filterGroupBuilder, $filterBuilder);
        $abstractSimpleObject = $this->createMock(SearchCriteriaInterface::class);
        $objectFactory->method('create')->willReturn($abstractSimpleObject);

        $this->payByLinkExpiredPaymentOrdersProvider = new PayByLinkExpiredPaymentOrdersProvider(
            $this->orderRepository,
            $this->orderPaymentRepository,
            $searchCriteriaBuilder,
            $filterBuilder,
            $filterGroupBuilder
        );
    }

    public function testProvideExpiredOrdersWithEmptyOrdersSuccessfully()
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([]);
        $this->orderPaymentRepository->method('getList')->willReturn($collection);
        $this->orderRepository->method('getList')->willReturn($collection);
        $expiredPaymentLinksOrders = $this->payByLinkExpiredPaymentOrdersProvider->provide();
        $this->assertEqualsCanonicalizing($expiredPaymentLinksOrders, []);
    }
}
