<?php
/**
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
use Adyen\Payment\Model\Notification;
use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;

/**
 * Helper class for anything related to the invoice entity
 *
 * @package Adyen\Payment\Helper
 */
class Order extends AbstractHelper
{
    /** @var Builder */
    private $transactionBuilder;

    /** @var Data */
    private $dataHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var OrderSender */
    private $orderSender;

    public function __construct(
        Context $context,
        Builder $transactionBuilder,
        Data $dataHelper,
        AdyenLogger $adyenLogger,
        OrderSender $orderSender
    )
    {
        parent::__construct($context);
        $this->transactionBuilder = $transactionBuilder;
        $this->dataHelper = $dataHelper;
        $this->adyenLogger = $adyenLogger;
        $this->orderSender = $orderSender;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return TransactionInterface|null
     * @throws \Exception
     */
    public function updatePaymentDetails(MagentoOrder $order, Notification $notification): ?TransactionInterface
    {
        //Set order state to new because with order state payment_review it is not possible to create an invoice
        if (strcmp($order->getState(), \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW) == 0) {
            $order->setState(MagentoOrder::STATE_NEW);
        }

        $paymentObj = $order->getPayment();

        // set pspReference as transactionId
        $paymentObj->setCcTransId($notification->getPspreference());
        $paymentObj->setLastTransId($notification->getPspreference());

        // set transaction
        $paymentObj->setTransactionId($notification->getPspreference());
        // Prepare transaction
        $transaction = $this->transactionBuilder->setPayment($paymentObj)
            ->setOrder($order)
            ->setTransactionId($notification->getPspreference())
            ->build(TransactionInterface::TYPE_AUTH);

        $transaction->setIsClosed(false);
        $transaction->save();

        return $transaction;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return MagentoOrder
     */
    public function addWebhookStatusHistoryComment(MagentoOrder $order, Notification $notification): MagentoOrder
    {
        $order->addStatusHistoryComment(__(sprintf(
            '%s webhook notification w/amount %s %s was processed',
            $notification->getEventCode(),
            $notification->getAmountCurrency(),
            $this->dataHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency())
        )), false);

        return $order;
    }

    /**
     * @param MagentoOrder $order
     */
    public function sendOrderMail(MagentoOrder $order)
    {
        try {
            $this->orderSender->send($order);
            $this->adyenLogger->addAdyenNotificationCronjob('Send order confirmation email to shopper');
        } catch (Exception $exception) {
            $this->adyenLogger->addAdyenNotificationCronjob(
                "Exception in Send Mail in Magento. This is an issue in the the core of Magento" .
                $exception->getMessage()
            );
        }
    }
}
