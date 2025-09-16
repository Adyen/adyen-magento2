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

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Config\Source\CaptureMode;
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
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        private readonly Config $configHelper,
        private readonly OrderHelper $orderHelper,
        private readonly Data $dataHelper,
        private readonly AdyenLogger $adyenLogger,
        private readonly ChargedCurrency $chargedCurrency
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
        $captureMode = $this->configHelper->getCaptureMode($storeId);

        if (strcmp($captureMode, CaptureMode::CAPTURE_MODE_MANUAL) !== 0) {
            /*
             * Expire webhook should not be obtained if auto capture is enabled.
             * If so, it might be an indicator of an incorrect plugin configuration.
             */
            $orderComment = __(
                'An unexpected %1 webhook has arrived even though auto capture is enabled, please check the plugin configuration! This webhook was skipped.',
                $notification->getEventCode()
            );
            $logMessage = $orderComment;
        } elseif ($isExpireWebhookIgnored) {
            $orderComment = __(
                'The remaining uncaptured authorisation with amount %1 has expired but no action has been taken as the %2 webhook was skipped.',
                $notification->getFormattedAmountCurrency(),
                $notification->getEventCode()
            );
            $logMessage = __(
                'The %1 webhook was skipped as configured in the plugin configuration `Ignore expire webhook`.',
                $notification->getEventCode()
            );
        } else {
            $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
            $orderAmountInMinorUnits = $this->dataHelper->formatAmount(
                $orderAmountCurrency->getAmount(),
                $orderAmountCurrency->getCurrencyCode()
            );
            // Indicates whether if the expired amount is equal to order grand total or not
            $expiredAmountEqualsToOrderAmount = $orderAmountInMinorUnits === $notification->getAmountValue();

            /*
             * The order can only be cancelled if the full amount has expired and there is no shipment.
             * In case of partial expirations and the cases with shipment, the plugin only logs
             * the relevant message and leaves the responsibility to merchants.
             */
            if ($expiredAmountEqualsToOrderAmount && !$order->hasShipments()) {
                $order = $this->orderHelper->holdCancelOrder($order, false);

                $orderComment = __(
                    'This order has been cancelled as the remaining uncaptured authorisation with amount %1 has expired.',
                    $notification->getFormattedAmountCurrency()
                );
                $logMessage = __(
                    'The %1 webhook has cancelled the order due to the expired remaining uncaptured authorisation.',
                    $notification->getEventCode()
                );
            } else {
                $orderComment = __(
                    'The remaining uncaptured authorisation with amount %1 has expired but no action has been taken due to shipment or partial capture. The order needs to be finalised manually.',
                    $notification->getFormattedAmountCurrency()
                );
                $logMessage = __(
                    'The %1 webhook was skipped due to shipment or partial capture.',
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
