<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Serialize\SerializerInterface;
use Adyen\Payment\API\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Helper\Webhook;

readonly class TokenWebhookAcceptor implements WebhookAcceptorInterface
{
    private const REQUIRED_FIELDS = [
        'eventId',
        'type',
        'data',
        'data.storedPaymentMethodId',
        'data.type',
        'data.shopperReference',
        'data.merchantAccount'
    ];

    public function __construct(
        private NotificationFactory $notificationFactory,
        private SerializerInterface $serializer,
        private AdyenLogger         $adyenLogger,
        private Webhook             $webhookHelper
    ) {}

    public function authenticate(array $payload): bool
    {
        // Token lifecycle webhooks do not support HMAC signature (yet), return true for now.
        return true;
    }

    public function validate(array $payload): bool
    {
        if (!$this->webhookHelper->isIpValid($payload, 'token webhook')) {
            return false;
        }

        foreach (self::REQUIRED_FIELDS as $fieldPath) {
            if (!$this->getNestedValue($payload, explode('.', $fieldPath))) {
                $this->adyenLogger->addAdyenNotification("Missing required field [$fieldPath] in token webhook", $payload);
                return false;
            }
        }

        $incomingMerchantAccount = $payload['data']['merchantAccount'];
        return $this->webhookHelper->isMerchantAccountValid($incomingMerchantAccount, $payload, 'token webhook');
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
