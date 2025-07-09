<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;

class WebhookAcceptorFactory
{
    public function __construct(
        private readonly StandardWebhookAcceptor $standardWebhookAcceptor,
        private readonly TokenWebhookAcceptor $tokenWebhookAcceptor
    ) {}

    public function getAcceptor(WebhookAcceptorType $type): WebhookAcceptorInterface
    {
        return match ($type) {
            WebhookAcceptorType::STANDARD => $this->standardWebhookAcceptor,
            WebhookAcceptorType::TOKEN    => $this->tokenWebhookAcceptor,
        };
    }
}
