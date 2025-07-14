<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Exception\AuthenticationException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Webhook\Exception\HMACKeyValidationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Adyen\Webhook\Receiver\HmacSignature;
use Magento\Framework\Serialize\SerializerInterface;

class StandardWebhookAcceptor implements WebhookAcceptorInterface
{
    public function __construct(
        private readonly Config      $configHelper,
        private readonly NotificationFactory  $notificationFactory,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly HmacSignature        $hmacSignature,
        private readonly SerializerInterface  $serializer,
        private readonly AdyenLogger          $adyenLogger
    ) { }


    /**
     * @throws InvalidDataException
     * @throws HMACKeyValidationException
     */
    public function validate(array $payload): bool
    {
        $hasHmac = $this->configHelper->getNotificationsHmacKey() &&
            $this->hmacSignature->isHmacSupportedEventCode($payload);

        if ($hasHmac && !$this->notificationReceiver->validateHmac(
                $payload,
                $this->configHelper->getNotificationsHmacKey()
            )) {
            $this->adyenLogger->addAdyenNotification("HMAC validation failed", $payload);
            return false;
        }

        return true;
    }


    private function toNotification(array $payload, string $mode): Notification
    {
        $notification = $this->notificationFactory->create();

        if (isset($payload['pspReference'])) {
            $notification->setPspreference($payload['pspReference']);
        }
        if (isset($payload['originalReference'])) {
            $notification->setOriginalReference($payload['originalReference']);
        }
        if (isset($payload['merchantReference'])) {
            $notification->setMerchantReference($payload['merchantReference']);
        }
        if (isset($payload['eventCode'])) {
            $notification->setEventCode($payload['eventCode']);
        }
        if (isset($payload['success'])) {
            $notification->setSuccess($payload['success']);
        }
        if (isset($payload['paymentMethod'])) {
            $notification->setPaymentMethod($payload['paymentMethod']);
        }
        if (isset($payload['reason'])) {
            $notification->setReason($payload['reason']);
        }
        if (isset($payload['done'])) {
            $notification->setDone($payload['done']);
        }
        if (isset($payload['amount'])) {
            $notification->setAmountValue($payload['amount']['value']);
            $notification->setAmountCurrency($payload['amount']['currency']);
        }
        if (isset($payload['additionalData'])) {
            $notification->setAdditionalData($this->serializer->serialize($payload['additionalData']));
        }

        if (!empty($payload['amount'])) {
            $notification->setAmountValue($payload['amount']['value']);
            $notification->setAmountCurrency($payload['amount']['currency']);
        }

        if (!empty($payload['additionalData'])) {
            $notification->setAdditionalData($this->serializer->serialize($payload['additionalData']));
        }

        $formattedDate = date('Y-m-d H:i:s');
        $notification->setCreatedAt($formattedDate);
        $notification->setUpdatedAt($formattedDate);

        if ($notification->isDuplicate()) {
            throw new \RuntimeException('Duplicate notification');
        }

        return $notification;
    }


    /**
     * @throws AuthenticationException
     * @throws InvalidDataException
     * @throws HMACKeyValidationException
     */
    public function toNotificationList(array $payload): array
    {
        $mode = $payload['live'] ?? '';

        if (!$this->notificationReceiver->validateNotificationMode(
            $mode,
            $this->configHelper->isDemoMode()
        )) {
            throw new AuthenticationException('Invalid notification mode.');
        }

        $notifications = [];

        foreach ($payload['notificationItems'] as $notificationItemWrapper) {
            $item = $notificationItemWrapper['NotificationRequestItem'] ?? $notificationItemWrapper;

            if (!$this->validate($item)) {
                throw new AuthenticationException('Notification failed authentication or validation.');
            }

            $notifications[] = $this->toNotification($item, $mode);
        }

        return $notifications;
    }
}
