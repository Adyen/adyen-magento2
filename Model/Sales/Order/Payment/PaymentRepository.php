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

namespace Adyen\Payment\Model\Sales\Order\Payment;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderPaymentSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Sales\Model\Order\Payment\Repository as SalesOrderPaymentRepository;
use Magento\Sales\Model\ResourceModel\Metadata;

class PaymentRepository extends SalesOrderPaymentRepository
{
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        Metadata $metaData,
        SearchResultFactory $searchResultFactory,
        ?CollectionProcessorInterface $collectionProcessor = null
    ) {
        parent::__construct($metaData, $searchResultFactory, $collectionProcessor);
    }

    public function getPaymentByCcTransId(string $ccTransId): ?OrderPaymentInterface
    {
        $filter = $this->filterBuilder->setField('cc_trans_id')
            ->setConditionType('eq')
            ->setValue($ccTransId)
            ->create();

        $filterGroup = $this->filterGroupBuilder->setFilters([$filter])->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$filterGroup])
            ->setPageSize(1)
            ->create();

        $paymentsList = $this->getList($searchCriteria);

        if ($paymentsList->getSize() > 0) {
            /** @var OrderPaymentInterface $payment */
            $payment = $paymentsList->getFirstItem();
            return $payment;
        } else {
            return null;
        }
    }
}
