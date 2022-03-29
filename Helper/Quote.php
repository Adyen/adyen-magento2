<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;

class Quote
{
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var OrderRepository
     */
    private $orderRepository;
    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteRepository $quoteRepository,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
    }

    public function getIsQuoteMultiShippingWithMerchantReference(string $merchantReference)
    {
        $orderList = $this->orderRepository->getList(
            $this->searchCriteriaBuilder->addFilter('increment_id', $merchantReference)->create()
        )->getItems();
        $order = reset($orderList);

        $quoteList = $this->cartRepository->getList(
            $this->searchCriteriaBuilder->addFilter('main_table.entity_id', $order->getQuoteId())->create()
        )->getItems();
        $quote = reset($quoteList);

        return $quote->getIsMultiShipping();
    }

    /**
     * Try to disable a quote after successful payment
     * @param $quoteId
     * @throws NoSuchEntityException
     */
    public function disableQuote($quoteId)
    {
        $quote = $this->quoteRepository->get($quoteId);
        if (!$quote || !$quote->getIsActive()) {
            return;
        }
        $quote->setIsActive(false);
        $this->cartRepository->save($quote);
    }

    /**
     * @param string $incrementId
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getQuoteByOrderIncrementId(string $incrementId): CartInterface
    {
        $orderFilter = $this->filterBuilder
            ->setField(OrderInterface::INCREMENT_ID)
            ->setConditionType('eq')
            ->setValue($incrementId)
            ->create();

        $this->searchCriteriaBuilder->addFilters([$orderFilter]);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResult = $this->orderRepository->getList($searchCriteria);

        if ($searchResult->getTotalCount() !== 1) {
            throw new NoSuchEntityException(__(
                sprintf('Order with increment id %s not found OR multiple orders exist', $incrementId)
            ));
        }

        $orders = $searchResult->getItems();
        /** @var OrderInterface $order */
        $order = reset($orders);

        return $this->cartRepository->get($order->getQuoteId());
    }
}
