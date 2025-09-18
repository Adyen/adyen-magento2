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

namespace Adyen\Payment\Api\Webhook;

use Adyen\Payment\Model\Notification;

interface WebhookAcceptorInterface
{
    /**
     * Validates and converts the incoming payload into one or more Notification objects.
     *
     * @param array $payload
     * @return Notification[]
     */
    public function getNotifications(array $payload): array;
}
