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

use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order;

interface WebhookHandlerInterface
{
    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order;
}
