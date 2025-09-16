<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\HMACKeyValidationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Adyen\Webhook\Receiver\HmacSignature;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Serialize\SerializerInterface;

class StandardWebhookAcceptor implements WebhookAcceptorInterface
{
    /**
     * @param NotificationFactory $notificationFactory
     * @param AdyenLogger $adyenLogger
     * @param Webhook $webhookHelper
     * @param Config $configHelper
     * @param NotificationReceiver $notificationReceiver
     * @param HmacSignature $hmacSignature
     * @param SerializerInterface $serializer
     * @param OrderHelper $orderHelper
 */
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly Webhook $webhookHelper,
        private readonly Config $configHelper,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly HmacSignature  $hmacSignature,
        private readonly SerializerInterface $serializer,
        private readonly OrderHelper $orderHelper,
    ) { }

    /**
     * @throws AuthenticationException
     * @throws InvalidDataException
     * @throws AlreadyExistsException|HMACKeyValidationException
     */
    public function getNotifications(array $payload): array
    {
        $notifications = [];
        $isLive = $payload['live'];
        $storeId = null;

        foreach ($payload['notificationItems'] as $notificationItemWrapper) {
            //Get the order here from the increment id
            $order = null;
            $merchantReference =  $notificationItemWrapper['NotificationRequestItem']['merchantReference'];
            if (!isset($merchantReference)) {
                $this->adyenLogger->addAdyenNotification('Missing required field [merchantReference] in token webhook', $payload);
                throw new InvalidDataException();
            }

            try {
                $order = $this->orderHelper->getOrderByIncrementId($merchantReference);
                $storeId = $order?->getStoreId();
            } catch (\Throwable $e) {
                $this->adyenLogger->addAdyenNotification(
                    sprintf('Could not load order for reference %s: %s', $merchantReference, $e->getMessage()),
                    $payload
                );
            }

            $item = $notificationItemWrapper['NotificationRequestItem'] ?? $notificationItemWrapper;
            $this->validate($item, $isLive, $storeId);

            $notifications[] = $this->toNotification($item, $isLive);
        }

        return $notifications;
    }

    /**
     * Validates the webhook environment mode and the HMAC signature
     *
     * @throws InvalidDataException
     * @throws HMACKeyValidationException
     * @throws AuthenticationException
     */
    private function validate(array $item, string $isLiveMode, ?int $storeId): void
    {
        if (!$this->notificationReceiver->validateNotificationMode($isLiveMode, $this->configHelper->isDemoMode($storeId))) {
            $this->adyenLogger->addAdyenNotification("Invalid environment for the webhook!", $item);
            throw new InvalidDataException();
        }

        $incomingMerchantAccount = $item['merchantAccountCode'];

        if (!$this->webhookHelper->isMerchantAccountValid($incomingMerchantAccount, $item, 'webhook', $storeId)) {
            $this->adyenLogger->addAdyenNotification(
                "Merchant account mismatch while handling the webhook!",
                $item
            );
            throw new InvalidDataException();
        }

        $hasHmac = $this->configHelper->getNotificationsHmacKey() &&
            $this->hmacSignature->isHmacSupportedEventCode($item);

        if ($hasHmac && !$this->notificationReceiver->validateHmac(
                $item,
                $this->configHelper->getNotificationsHmacKey()
            )) {
            $this->adyenLogger->addAdyenNotification("HMAC validation failed", $item);

            throw new AuthenticationException();
        }
    }

    /**
     * @throws AlreadyExistsException
     */
    private function toNotification(array $payload, string $isLive): Notification
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

        $notification->setLive($isLive);

        if ($notification->isDuplicate()) {
            throw new AlreadyExistsException(__('Webhook already exists!'));
        }

        return $notification;
    }
}
