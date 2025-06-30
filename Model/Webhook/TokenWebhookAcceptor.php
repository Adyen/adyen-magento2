<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Serialize\SerializerInterface;
use Adyen\Payment\API\Webhook\WebhookAcceptorInterface;

class TokenWebhookAcceptor implements WebhookAcceptorInterface
{
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        private readonly SerializerInterface $serializer,
        private readonly AdyenLogger $adyenLogger
    ) {}

    public function canHandle(array $payload): bool
    {
        return isset($payload['type']) && str_contains($payload['type'], 'token');
    }

    public function authenticate(array $payload): bool
    {
        // Token lifecycle webhooks do not support HMAC signature (yet), return true for now.
        return true;
    }

    public function validate(array $payload): bool
    {
        // Required fields
        $requiredFields = ['eventId', 'type', 'data', 'data.storedPaymentMethodId', 'data.type'];
        foreach ($requiredFields as $field) {
            if (!$this->getNestedValue($payload, explode('.', $field))) {
                $this->adyenLogger->addAdyenNotification("Missing required field [$field] in token webhook", $payload);
                return false;
            }
        }

        return true;
    }

    public function toNotification(array $payload, string $mode): Notification
    {
        $notification = $this->notificationFactory->create();

        $pspReference = $payload['data']['storedPaymentMethodId'] ?? $payload['eventId'] ?? uniqid('token_', true);
        $merchantReference = $payload['data']['shopperReference'] ?? null;

        $notification->setPspreference($pspReference);
        $notification->setOriginalReference($payload['eventId'] ?? null);
        $notification->setMerchantReference($merchantReference);
        $notification->setEventCode($payload['eventType'] ?? $payload['type'] ?? 'TOKEN');
        $notification->setPaymentMethod($payload['data']['type'] ?? null);
        $notification->setLive($mode);
        $notification->setSuccess('true'); // Always true for token events
        $notification->setReason('Token lifecycle event');

        // Store full payload in additionalData
        $notification->setAdditionalData($this->serializer->serialize($payload));

        $formattedDate = date('Y-m-d H:i:s');
        $notification->setCreatedAt($formattedDate);
        $notification->setUpdatedAt($formattedDate);

        return $notification;
    }

    private function getNestedValue(array $array, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return null;
            }
            $array = $array[$key];
        }
        return $array;
    }
}
