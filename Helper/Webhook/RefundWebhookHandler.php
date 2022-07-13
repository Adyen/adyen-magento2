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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
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

    public function __construct(
        Order $orderHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->orderHelper = $orderHelper;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @throws \Exception
     */
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

        return $this->orderHelper->refundOrder($order, $notification);
    }
}
