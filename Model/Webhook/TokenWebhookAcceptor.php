<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\Exception\AuthenticationException;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Serialize\SerializerInterface;
use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Helper\Webhook;

class TokenWebhookAcceptor implements WebhookAcceptorInterface
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
        private readonly NotificationFactory $notificationFactory,
        private readonly SerializerInterface $serializer,
        private readonly AdyenLogger $adyenLogger,
        private readonly Webhook $webhookHelper
    ) {}

    public function validate(array $payload): bool
    {
        if (!$this->webhookHelper->isIpValid($payload, 'token webhook')) {
            $this->adyenLogger->addAdyenNotification("IP validation failed for token webhook", $payload);
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

        $pspReference = $payload['data']['storedPaymentMethodId'];
        $merchantReference = $payload['data']['shopperReference'] ?? null;

        $notification->setPspreference($pspReference);

        if (isset($payload['eventId'])) {
            $notification->setOriginalReference($payload['eventId']);
        }

        if (isset($merchantReference)) {
            $notification->setMerchantReference($merchantReference);
        }

        if (isset($payload['eventType'])) {
            $notification->setEventCode($payload['eventType']);
        } elseif (isset($payload['type'])) {
            $notification->setEventCode($payload['type']);
        } else {
            $notification->setEventCode('TOKEN');
        }

        if (isset($payload['data']['type'])) {
            $notification->setPaymentMethod($payload['data']['type']);
        }

        $notification->setLive($mode);
        $notification->setSuccess('true');
        $notification->setReason('Token lifecycle event');
        $notification->setAdditionalData($this->serializer->serialize($payload));

        $formattedDate = date('Y-m-d H:i:s');
        $notification->setCreatedAt($formattedDate);
        $notification->setUpdatedAt($formattedDate);

        // 💡 Add duplicate check here
        if ($notification->isDuplicate()) {
            throw new \RuntimeException('Duplicate token notification');
        }

        return $notification;
    }

    /**
     * @throws AuthenticationException
     */
    public function toNotificationList(array $payload): array
    {
        if (!$this->validate($payload)) {
            throw new AuthenticationException('Token webhook failed authentication or validation.');
        }

        return [$this->toNotification($payload, $payload['environment'] ?? 'test')];
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
