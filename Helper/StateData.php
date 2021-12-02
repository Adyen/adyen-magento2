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
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\ResourceModel\StateData as StateDataResourceModel;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Adyen\Payment\Model\StateData as StateDataModel;
use Adyen\Payment\Model\StateDataFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment\Collection;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;

class StateData
{

    const CLEANUP_RESULT_CODES = array(
        "Authorised"
    );
    const STATE_DATA_KEY = 'stateData';
    const STATE_DATA_LIKE_STRING = '%stateData%';
    const ADDITIONAL_INFORMATION_KEY='additional_information';

    /**
     * @var StateDataCollection
     */
    private $stateDataCollection;

    /**
     * @var StateDataFactory
     */
    private $stateDataFactory;

    /**
     * @var StateDataResourceModel
     */
    private $stateDataResourceModel;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $orderPaymentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Collection
     */
    private $quotePaymentCollection;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    public function __construct(
        StateDataCollection $stateDataCollection,
        StateDataFactory $stateDataFactory,
        StateDataResourceModel $stateDataResourceModel,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CartRepositoryInterface $cartRepository,
        Collection $quotePaymentCollection
    ) {
        $this->stateDataCollection = $stateDataCollection;
        $this->stateDataFactory = $stateDataFactory;
        $this->stateDataResourceModel = $stateDataResourceModel;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->cartRepository = $cartRepository;
        $this->quotePaymentCollection = $quotePaymentCollection;
    }

    public function getSalesOrderPaymentsWithStateData()
    {
        return $this->orderPaymentRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter(self::ADDITIONAL_INFORMATION_KEY, self::STATE_DATA_LIKE_STRING, "like")
                ->create()
        )->getItems();
    }

    public function getQuotePaymentsWithStateData()
    {
        return $this->quotePaymentCollection
            ->addFieldToFilter(self::ADDITIONAL_INFORMATION_KEY, ['like' => self::STATE_DATA_LIKE_STRING])
            ->getItems();
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param $resultCode
     * @throws \Exception
     */
    public function cleanQuoteStateData($quoteId, $resultCode)
    {
        if (in_array($resultCode, self::CLEANUP_RESULT_CODES)) {
            $rows = $this->stateDataCollection->getStateDataRowsWithQuoteId($quoteId)->getItems();
            foreach ($rows as $row) {
                $this->deleteStateData($row->getData('entity_id'));
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function deleteStateData($entityId)
    {
        /** @var StateDataModel $stateData */
        $stateData = $this->stateDataFactory->create()->load($entityId);
        $this->stateDataResourceModel->delete($stateData);
    }
}
