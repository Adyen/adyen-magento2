<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\API\Webhook\WebhookAcceptorInterface;

class WebhookAcceptorFactory
{
    public function __construct(
        private StandardWebhookAcceptor $standardWebhookAcceptor,
        private TokenWebhookAcceptor    $tokenWebhookAcceptor
    ) {}

    public function getAcceptor(string $type): WebhookAcceptorInterface
    {
        return match ($type) {
            WebhookAcceptorInterface::TYPE_STANDARD => $this->standardWebhookAcceptor,
            WebhookAcceptorInterface::TYPE_TOKEN    => $this->tokenWebhookAcceptor,
            default                                  => throw new \InvalidArgumentException("Unsupported webhook type [$type]"),
        };
    }
}
