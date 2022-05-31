<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;


use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Webhook\PaymentStates;
use Magento\Sales\Model\Order as MagentoOrder;

class RefundWebhookHandler implements WebhookHandlerInterface
{
    /** @var Order */
    private $orderHelper;

    /** @var Config */
    private $configHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var OrderPaymentCollectionFactory */
    private $adyenOrderPaymentCollectionFactory;

    /** @var Data */
    private $adyenDataHelper;

    /** @var AdyenOrderPayment */
    private $adyenOrderPaymentHelper;

    public function __construct(
        Order $orderHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger,
        OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory,
        Data $adyenDataHelper,
        AdyenOrderPayment $adyenOrderPaymentHelper
    )
    {
        $this->orderHelper = $orderHelper;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
        $this->adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        if ($transitionState === PaymentStates::STATE_PAID) {
            $this->orderHelper->addRefundFailedNotice($notification);

            return $order;
        }

        $ignoreRefundNotification = $this->configHelper->getConfigData(
            'ignore_refund_notification',
            'adyen_abstract',
            $order->getStoreId()
        );

        if ($ignoreRefundNotification) {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Config to ignore refund notification is enabled. Notification %s will be ignored',
                $notification->getId()
            ));

            return $order;
        }

        // check if it is a partial payment if so save the refunded data
        // TODO: Refactor this to use adyen_order_payment
        if ($notification->getOriginalReference() != "") {

            /** @var OrderPaymentInterface $orderPayment */
            $orderPayment = $this->adyenOrderPaymentCollectionFactory
                ->create()
                ->addFieldToFilter(Notification::PSPREFRENCE, $notification->getOriginalReference())
                ->getFirstItem();

            if ($orderPayment->getEntityId() > 0) {
                $this->adyenOrderPaymentHelper->refundAdyenOrderPayment($orderPayment, $notification);
                $this->adyenLogger->addAdyenDebug(sprintf(
                    'Refunding %s from AdyenOrderPayment %s',
                    $notification->getAmountCurrency() . $notification->getAmountValue(),
                    $orderPayment->getEntityId()
                ));
            } else {
                $this->adyenLogger->addAdyenDebug(sprintf(
                    'AdyenOrderPayment with pspReference %s was not found. This should be linked to order %s',
                    $notification->getOriginalReference(),
                    $order->getRemoteIp()
                ));
            }
        }

        /*
         * Don't create a credit memo if refund is initialized in Magento
         * because in this case the credit memo already exists.
         * Refunds initialized in Magento have a suffix such as '-refund', '-capture' or '-capture-refund' appended
         * to the original reference.
         */
        $lastTransactionId = $order->getPayment()->getLastTransId();
        $matches = $this->adyenDataHelper->parseTransactionId($lastTransactionId);
        if (($matches['pspReference'] ?? '') == $notification->getOriginalReference() && empty($matches['suffix'])) {
            // refund is done through adyen backoffice so create a credit memo
            if ($order->canCreditmemo()) {
                $amount = $this->adyenDataHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency());
                $order->getPayment()->registerRefundNotification($amount);

                $this->adyenLogger->addAdyenDebug(sprintf('Created credit memo for order %s', $order->getIncrementId()));
                $order->addStatusHistoryComment(__('Adyen Refund Successfully completed'), $order->getStatus());
            } else {
                $this->adyenLogger->addAdyenDebug(sprintf(
                    'Could not create a credit memo for order %s while processing notification %s',
                    $order->getIncrementId(),
                    $notification->getId()
                ));
            }
        } else {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Did not create a credit memo for order %s because refund was done through Magento', $order->getIncrementId()
            ));
        }

        return $order;
    }
}