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
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;

class OrderClosedWebhookHandler implements WebhookHandlerInterface
{
    /** @var AdyenOrderPayment */
    private $adyenOrderPaymentHelper;

    /** @var OrderHelper */
    private $orderHelper;

    /** @var Config */
    private $configHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var OrderPaymentCollectionFactory */
    private $adyenOrderPaymentCollectionFactory;

    /**
     * @param AdyenOrderPayment $adyenOrderPayment
     * @param OrderHelper $orderHelper
     * @param Config $configHelper
     * @param OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        AdyenOrderPayment $adyenOrderPayment,
        OrderHelper $orderHelper,
        Config $configHelper,
        OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory,
        AdyenLogger $adyenLogger
    ) {
        $this->adyenOrderPaymentHelper = $adyenOrderPayment;
        $this->orderHelper = $orderHelper;
        $this->configHelper = $configHelper;
        $this->adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @param string $transitionState
     * @return MagentoOrder
     * @throws \Exception
     */
    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        if ($notification->isSuccessful()) {
            $order->addCommentToStatusHistory(__('This order has been successfully completed.'));
        } else {
            /** @var OrderPaymentInterface $orderPayment */
            $capturedAdyenOrderPayments = $this->adyenOrderPaymentCollectionFactory
                ->create()
                ->addFieldToFilter('payment_id', $order->getPayment()->getEntityId())
                ->addFieldToFilter('capture_status', [
                    'in' => [
                        OrderPaymentInterface::CAPTURE_STATUS_AUTO_CAPTURE,
                        OrderPaymentInterface::CAPTURE_STATUS_MANUAL_CAPTURE
                    ]
                ])
                ->getItems();

            if (!empty($capturedAdyenOrderPayments)) {
                /*
                 * Update adyen_order_payment table to reflect the refunds on the Adyen side.
                 * It's not possible to create a credit memo.
                 */
                /* @var $adyenOrderPayment OrderPaymentInterface */
                foreach ($capturedAdyenOrderPayments as $adyenOrderPayment) {
                    $this->adyenOrderPaymentHelper->refundFullyAdyenOrderPayment($adyenOrderPayment);
                }

                $order->addCommentToStatusHistory(__('All the funds captured/settled will be refunded by Adyen.'));
                $this->adyenLogger->addAdyenNotification(
                    'All the funds captured/settled will be refunded by Adyen.',
                    [
                        'pspReference' => $notification->getPspreference(),
                        'merchantReference' => $notification->getMerchantReference()
                    ]
                );
            }

            if ($order->canCancel() && $this->configHelper->getNotificationsCanCancel($order->getStoreId())) {
                $this->orderHelper->holdCancelOrder($order, true);

                $this->adyenLogger->addAdyenNotification(
                    'This order has been cancelled by the ORDER_CLOSED notification.',
                    [
                        'pspReference' => $notification->getPspreference(),
                        'merchantReference' => $notification->getMerchantReference()
                    ]
                );
            }
        }

        return $order;
    }
}
