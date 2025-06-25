<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\API\Webhook\WebhookAcceptorInterface;

class WebhookAcceptorFactory
{
    /**
     * @var WebhookAcceptorInterface[]
     */
    private array $acceptors;

    public function __construct(array $acceptors = [])
    {
        $this->acceptors = $acceptors;
    }

    public function getAcceptor(array $payload): WebhookAcceptorInterface
    {
        foreach ($this->acceptors as $acceptor) {
            if ($acceptor->canHandle($payload)) {
                return $acceptor;
            }
        }

        throw new \InvalidArgumentException('No suitable webhook acceptor found for this payload.');
    }
}
