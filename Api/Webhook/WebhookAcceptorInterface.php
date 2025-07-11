<?php

namespace Adyen\Payment\Api\Webhook;

use Adyen\Payment\Model\Notification;

interface WebhookAcceptorInterface
{
    public function validate(array $payload): bool;

    /**
     * Converts the incoming payload into one or more Notification objects.
     * Should throw UnauthorizedWebhookException on failure.
     *
     * @param array $payload
     * @return Notification[]
     */
    public function toNotificationList(array $payload): array;
}
