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

use Adyen\Payment\Helper\Order;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;


class RefundFailedWebhookHandler implements WebhookHandlerInterface
{
    /** @var Order */
    private $orderHelper;

    public function __construct(Order $orderHelper)
    {
        $this->orderHelper = $orderHelper;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $this->orderHelper->addRefundFailedNotice($notification);

        return $order;
    }
}
