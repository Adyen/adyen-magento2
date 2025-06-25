<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\API\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Magento\Framework\Serialize\SerializerInterface;

class TokenWebhookAcceptor implements WebhookAcceptorInterface
{
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        private readonly SerializerInterface $serializer
    ) {}

    public function canHandle(array $payload): bool
    {
        return isset($payload['dataType']) && $payload['dataType'] === 'token';
    }

    public function authenticate(array $payload): bool
    {
        // Placeholder for token lifecycle auth, if needed
        return true;
    }

    public function validate(array $payload): bool
    {
        // Placeholder — adjust logic as needed
        return true;
    }

    public function toNotification(array $payload, string $notificationMode): Notification
    {
        $notification = $this->notificationFactory->create();
        $notification->setEventCode('TOKEN_LIFECYCLE');
        $notification->setMerchantReference($payload['merchantReference'] ?? null);
        $notification->setPspreference($payload['tokenId'] ?? uniqid('token_', true));
        $notification->setLive($notificationMode);
        $notification->setSuccess('true');
        $notification->setReason('Token lifecycle event');

        if (!empty($payload)) {
            $notification->setAdditionalData($this->serializer->serialize($payload));
        }

        $formattedDate = date('Y-m-d H:i:s');
        $notification->setCreatedAt($formattedDate);
        $notification->setUpdatedAt($formattedDate);

        return $notification;
    }
}

