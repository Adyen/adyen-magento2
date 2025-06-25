<?php

namespace Adyen\Payment\API\Webhook;

interface WebhookAcceptorInterface
{
    public function canHandle(array $payload): bool;
    public function authenticate(array $payload): bool;
    public function validate(array $payload): bool;
    public function toNotification(array $payload, string $mode): Notification;
}

