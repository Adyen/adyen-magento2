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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as AdyenOrderPaymentCollection;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

/**
 * Helper class for anything related to the adyen_order_payment entity
 *
 * Class AdyenOrderPayment
 * @package Adyen\Payment\Helper
 */
class AdyenOrderPayment extends AbstractHelper
{
    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var AdyenOrderPaymentCollection
     */
    protected $adyenOrderPaymentCollection;

    /**
     * @var Data
     */
    protected $adyenDataHelper;

    /**
     * @var ChargedCurrency
     */
    protected $adyenChargedCurrencyHelper;

    /**
     * AdyenOrderPayment constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param AdyenOrderPaymentCollection $adyenOrderPaymentCollection
     * @param Data $adyenDataHelper
     * @param ChargedCurrency $adyenChargedCurrencyHelper
     */
    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger,
        AdyenOrderPaymentCollection $adyenOrderPaymentCollection,
        Data $adyenDataHelper,
        ChargedCurrency $adyenChargedCurrencyHelper
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->adyenOrderPaymentCollection = $adyenOrderPaymentCollection;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->adyenChargedCurrencyHelper = $adyenChargedCurrencyHelper;
    }

    /**
     * Check if the total amount of the order has been authorised
     *
     * @param Order $order
     */
    public function isTotalAmountAuthorized(Order $order)
    {
        // Get total amount currently authorised
        $queryResult = $this->adyenOrderPaymentCollection
            ->create()
            ->getTotalAmount($order->getPayment()->getEntityId());

        if ($queryResult && isset($queryResult[0]) && is_array($queryResult[0])) {
            $totalAmountAuthorized = $queryResult[0]['total_amount'];
        } else {
            $this->adyenLogger->error(
                sprintf('Unable to obtain the total amount authorized of order %s', $order->getIncrementId())
            );

            return false;
        }

        $orderTotal = $this->adyenChargedCurrencyHelper->getOrderAmountCurrency($order, false);
        $orderTotalAmount = $this->adyenDataHelper->formatAmount($orderTotal->getAmount(), $orderTotal->getCurrencyCode());
        $totalAmountAuthorized = $this->adyenDataHelper->formatAmount($totalAmountAuthorized, $orderTotal->getCurrencyCode());

        if ($totalAmountAuthorized === $orderTotalAmount) {
            return true;
        }

        return false;
    }

}