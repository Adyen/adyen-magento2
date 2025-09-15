<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class ExpireWebhookHandler implements WebhookHandlerInterface
{
    /**
     * @param Config $configHelper
     * @param OrderHelper $orderHelper
     * @param Data $dataHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly Config $configHelper,
        private readonly OrderHelper $orderHelper,
        private readonly Data $dataHelper,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param string $transitionState
     * @return Order
     * @throws LocalizedException
     */
    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order
    {
        $storeId = $order->getStoreId();
        $isExpireWebhookIgnored = $this->configHelper->isExpireWebhookIgnored($storeId);

        if ($isExpireWebhookIgnored) {
            $orderComment = __(
                'The remaining uncaptured authorisation with amount %1 has expired but the order has not been cancelled since the %2 webhook was skipped.',
                $notification->getFormattedAmountCurrency(),
                $notification->getEventCode()
            );
            $logMessage = __(
                'The %1 webhook was skipped as configured in the plugin configuration `Ignore expire webhook`.',
                $notification->getEventCode()
            );
        } else {
            $amount = $this->dataHelper->originalAmount(
                $notification->getAmountValue(),
                $notification->getAmountCurrency()
            );

            if ($order->getTotalDue() == $amount) {
                $order = $this->orderHelper->holdCancelOrder($order, false);

                $orderComment = __(
                    'This order has been completed/cancelled as the remaining uncaptured authorisation with amount %1 has expired.',
                    $notification->getFormattedAmountCurrency()
                );
                $logMessage = __(
                    'The %1 webhook has completed/cancelled the order due to the expired remaining uncaptured authorisation.',
                    $notification->getEventCode()
                );
            } else {
                $orderComment = __(
                    'The remaining uncaptured authorisation with amount %1 has expired but the order has not been cancelled due to the amount mismatch. The order needs to be finalised manually.',
                    $notification->getFormattedAmountCurrency()
                );
                $logMessage = __(
                    'The %1 webhook was skipped due to the amount mismatch. The order needs to be finalised manually.',
                    $notification->getEventCode()
                );
            }
        }

        if (isset($orderComment)) {
            $order->addCommentToStatusHistory($orderComment);
        }

        if (isset($logMessage)) {
            $this->adyenLogger->addAdyenNotification($logMessage, [
                'pspReference' => $notification->getPspreference(),
                'merchantReference' => $notification->getMerchantReference()
            ]);
        }

        return $order;
    }
}
