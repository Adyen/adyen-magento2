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

use Magento\Sales\Model\Order;
use Adyen\Payment\Model\Notification;

class RecurringTokenAlreadyExistingWebhookHandler implements WebhookHandlerInterface
{
    /**
     * Handle the recurring.token.alreadyExisting webhook.
     *
     * @param Order $order
     * @param Notification $notification
     * @param string $transitionState
     * @return Order
     */
    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order {
        $order->addCommentToStatusHistory(
            __('Recurring token already exists and had been linked to this customer.')
        );

        return $order;
    }
}
