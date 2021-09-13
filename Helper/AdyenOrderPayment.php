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

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
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
     * @var OrderPaymentResourceModel
     */
    private $orderPaymentResourceModel;

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
        ChargedCurrency $adyenChargedCurrencyHelper,
        OrderPaymentResourceModel $orderPaymentResourceModel
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->adyenOrderPaymentCollection = $adyenOrderPaymentCollection;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->adyenChargedCurrencyHelper = $adyenChargedCurrencyHelper;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
    }

    /**
     * Check if the total amount of the order has been authorised
     *
     * @param Order $order
     * @return bool
     */
    public function isTotalAmountAuthorized(Order $order): bool
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

    /**
     * Check if ANY adyen_order_payment linked to this order requires a manual capture
     *
     * @param Order $order
     * @return bool
     */
    public function requiresManualCapture(Order $order): bool
    {
        $requireManualCapture = false;
        $payment = $order->getPayment();
        $adyenOrderPayments = $this->orderPaymentResourceModel->getLinkedAdyenOrderPayments($payment->getEntityId());

        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            if ($adyenOrderPayment[OrderPaymentInterface::CAPTURE_STATUS] === OrderPaymentInterface::CAPTURE_STATUS_NO_CAPTURE) {
                $requireManualCapture = true;
            }
        }

        return $requireManualCapture;
    }

    /**
     * Go trough the adyen_order_payment entries linked and get the amount that has been captured
     *
     * @param Order $order
     * @return int|null
     */
    public function getCapturedAmount(Order $order): ?int
    {
        $orderAmountCents = null;
        $paymentId = $order->getPayment()->getEntityId();
        // Get total amount currently captured
        $queryResult = $this->adyenOrderPaymentCollection
            ->create()
            ->getTotalAmount($paymentId, true);

        if ($queryResult && isset($queryResult[0]) && is_array($queryResult[0])) {
            $amount = $queryResult[0]['total_amount'];
            $orderAmountCents = $this->adyenDataHelper->formatAmount($amount, $order->getOrderCurrencyCode());
        }

        return $orderAmountCents;
    }
}
