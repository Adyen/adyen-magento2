<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Helper\Order;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\PaymentStates;
use Magento\Sales\Model\Order as MagentoOrder;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Config;


abstract class AbstractDisputeWebhookHandler implements WebhookHandlerInterface
{
    /** @var Order */
    private $orderHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var Config */
    private $configHelper;

    public function __construct(Order $orderHelper, AdyenLogger $adyenLogger, Config $configHelper)
    {
        $this->orderHelper = $orderHelper;
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $ignoreDisputeNotifications = $this->configHelper->getConfigData(
            'ignore_dispute_notification',
            'adyen_abstract',
            $order->getStoreId()
        );

        if ($transitionState === PaymentStates::STATE_REFUNDED && !$ignoreDisputeNotifications){
            $order = $this->orderHelper->refundOrder($order, $notification);
            $this->adyenLogger->addAdyenNotification(sprintf(
                'The order has been updated by the %s notification. ',
                $notification->getEventCode(),
            ), [
                'pspReference' => $order->getPayment()->getData('adyen_psp_reference'),
                'merchantReference' => $order->getPayment()->getData('entity_id')
            ]);
        }
        elseif ($ignoreDisputeNotifications){
            $this->adyenLogger->addAdyenNotification(sprintf(
                'Config to ignore dispute notification is enabled. Notification %s will be ignored',
                $notification->getId()
            ), [
                'pspReference' => $notification->getPspreference(),
                'merchantReference' => $notification->getMerchantReference()
                ]);
        }
        else {
            $this->orderHelper->addWebhookStatusHistoryComment($order, $notification);
            $this->adyenLogger->addAdyenNotification(sprintf(
                'There is a %s notification for the order.',
                $notification->getEventCode(),
            ), [
                'pspReference' => $order->getPayment()->getData('adyen_psp_reference'),
                'merchantReference' => $order->getPayment()->getData('entity_id')
            ]);
        }
        return $order;
    }

}
