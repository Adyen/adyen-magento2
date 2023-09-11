<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order;

class OrderOpenedWebhookHandler implements WebhookHandlerInterface
{
    private AdyenLogger $adyenLogger;

    public function __construct(
        AdyenLogger $adyenLogger
    ) {
        $this->adyenLogger = $adyenLogger;
    }

    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order
    {
        if ($notification->isSuccessful()) {
            $order->addCommentToStatusHistory(__("Adyen order has been successfully created for partial payments."));
        } else {
            $this->adyenLogger->addAdyenNotification(__("An error occurred while creating partial payment order!"), [
                'pspReference' => $notification->getPspreference(),
                'merchantReference' => $notification->getMerchantReference()
            ]);
        }

        return $order;
    }
}
