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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron\Providers;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class PayByLinkExpiredPaymentOrdersProvider implements OrdersProviderInterface
{
    /**
     * @var CollectionFactory $orderCollectionFactory
     */
    protected $orderRepository;

    /**
     * @var AdyenLogger $adyenLogger
     */
    protected $adyenLogger;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var FilterBuilder
     */
    private $filterBuilder;
    /**
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     * ServerIpAddress constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
    }

    public function getProviderName()
    {
        return "Adyen Pay by Link expired";
    }

    /**
     * Provides orders paid with PBL in state new that have expired
     *
     * @return OrderInterface[]
     */
    public function provide()
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$this->getPaymentMethodFilterGroup(), $this->getStateFilterGroup()])
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        return $this->getOrdersWithExpiredPbl($orders);
    }

    /**
     * @return \Magento\Framework\Api\Search\FilterGroup
     */
    private function getPaymentMethodFilterGroup()
    {
        $paymentMethodFilter = $this->filterBuilder->setField('extension_attribute_payment_method.method')
            ->setConditionType('eq')
            ->setValue(AdyenPayByLinkConfigProvider::CODE)
            ->create();

        return $this->filterGroupBuilder->setFilters([$paymentMethodFilter])->create();
    }

    /**
     * @return \Magento\Framework\Api\Search\FilterGroup
     */
    private function getStateFilterGroup()
    {
        $stateFilter = $this->filterBuilder->setField('state')
            ->setConditionType('eq')
            ->setValue(Order::STATE_NEW)
            ->create();

        return $this->filterGroupBuilder->setFilters([$stateFilter])->create();
    }

    /**
     * @param $orders OrderInterface[]
     * @return OrderInterface[]
     */
    private function getOrdersWithExpiredPbl($orders)
    {
        $now = new \DateTime();
        $expiredOrders = [];
        foreach ($orders as $order) {
            $paymentAdditionalInformation = $order->getPayment()->getAdditionalInformation();
            $pblExpiryDateString = $paymentAdditionalInformation[AdyenPayByLinkConfigProvider::EXPIRES_AT_KEY] ?? false;
            if ($pblExpiryDateString) {
                $pblExpiryDate = \DateTime::createFromFormat(DATE_ATOM, $pblExpiryDateString);
                if ($now > $pblExpiryDate) {
                    $expiredOrders[] = $order;
                }
            }
        }
        return $expiredOrders;
    }
}
