<?php

namespace Adyen\Payment\Api\Webhook;

use Adyen\Payment\Model\Notification;

interface WebhookAcceptorInterface
{
    public const TYPE_STANDARD = 'standard';
    public const TYPE_TOKEN = 'token';
    public function authenticate(array $payload): bool;
    public function validate(array $payload): bool;
    public function toNotification(array $payload, string $mode): Notification;
}

