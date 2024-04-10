<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron\Providers;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class PayByLinkExpiredPaymentOrdersProvider implements OrdersProviderInterface
{
    protected OrderRepositoryInterface $orderRepository;
    protected OrderPaymentRepositoryInterface $orderPaymentRepository;
    protected AdyenLogger $adyenLogger;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private FilterBuilder $filterBuilder;
    private FilterGroupBuilder $filterGroupBuilder;

    public function __construct(
        OrderRepositoryInterface        $orderRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        SearchCriteriaBuilder           $searchCriteriaBuilder,
        FilterBuilder                   $filterBuilder,
        FilterGroupBuilder              $filterGroupBuilder
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
    }

    public function getProviderName(): string
    {
        return "Adyen Pay by Link expired";
    }

    /**
     * Provides orders paid with PBL in state new that have expired
     *
     * @return OrderInterface[]
     * @throws InputException
     */
    public function provide(): array
    {
        $expiredOrderIds = $this->getExpiredOrderIds();

        return $this->getExpiredOrders($expiredOrderIds);
    }

    /**
     * @retrun int[]
     * @throws InputException
     */
    protected function getExpiredOrderIds(): array
    {
        $orderPayments = $this->getPendingPayByLinkPayments();
        $expiredOrderIds = [];
        $now = new \DateTime();

        foreach ($orderPayments as $orderPayment) {
            /** @var OrderPaymentInterface $orderPayment */
            $paymentAdditionalInformation = $orderPayment->getAdditionalInformation();
            $pblExpiryDateString = $paymentAdditionalInformation[AdyenPayByLinkConfigProvider::EXPIRES_AT_KEY] ?? false;

            if ($pblExpiryDateString) {
                $pblExpiryDate = \DateTime::createFromFormat(DATE_ATOM, $pblExpiryDateString);
                if ($now > $pblExpiryDate) {
                    $expiredOrderIds[] = $orderPayment->getParentId();
                }
            }
        }
        return $expiredOrderIds;
    }

    /**
     * @return OrderInterface[]
     * @throws InputException
     */
    protected function getExpiredOrders($expiredOrderIds): array
    {
        $sortOrder = new SortOrder();
        $sortOrder->setField(OrderInterface::CREATED_AT)->setDirection('ASC');

        $stateFilter = $this->filterBuilder->setField('state')
            ->setConditionType('eq')
            ->setValue(Order::STATE_NEW)
            ->create();

        $orderIdFilter = $this->filterBuilder->setField('entity_id')
            ->setConditionType('in')
            ->setValue($expiredOrderIds)
            ->create();

        $stateFilterGroup = $this->filterGroupBuilder->setFilters([$stateFilter])->create();
        $orderIdFilterGroup = $this->filterGroupBuilder->setFilters([$orderIdFilter])->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$stateFilterGroup, $orderIdFilterGroup])
            ->setSortOrders([$sortOrder])
            ->setPageSize(500)
            ->create();

        return $this->orderRepository->getList($searchCriteria)->getItems();
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderPaymentInterface[]
     * @throws InputException
     */
    protected function getPendingPayByLinkPayments(): array
    {
        $sortOrder = new SortOrder();
        $sortOrder->setField('parent_id')->setDirection('DESC');

        $paymentMethodFilter = $this->filterBuilder->setField('method')
            ->setConditionType('eq')
            ->setValue(PaymentMethods::ADYEN_PAY_BY_LINK)
            ->create();

        $pspreferenceFilter = $this->filterBuilder->setField('adyen_psp_reference')
            ->setConditionType('null')
            ->create();

        $paymentMethodFilterGroup = $this->filterGroupBuilder->setFilters([$paymentMethodFilter])->create();
        $pspreferenceFilterGroup = $this->filterGroupBuilder->setFilters([$pspreferenceFilter])->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$paymentMethodFilterGroup, $pspreferenceFilterGroup])
            ->setSortOrders([$sortOrder])
            ->setPageSize(500)
            ->create();

        return $this->orderPaymentRepository->getList($searchCriteria)->getItems();
    }
}
