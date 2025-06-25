<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\API\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\IpAddress;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Adyen\Webhook\Receiver\HmacSignature;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class StandardWebhookAcceptor implements WebhookAcceptorInterface
{
    public function __construct(
        private readonly Config $configHelper,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly HmacSignature $hmacSignature,
        private readonly IpAddress $ipAddressHelper,
        private readonly RemoteAddress $remoteAddress,
        private readonly NotificationFactory $notificationFactory,
        private readonly SerializerInterface $serializer,
        private readonly AdyenLogger $adyenLogger
    ) {}

    public function canHandle(array $payload): bool
    {
        return isset($payload['eventCode']);
    }

    public function authenticate(array $payload): bool
    {
        $merchantAccount = $this->configHelper->getMerchantAccount() ?: $this->configHelper->getMotoMerchantAccounts();
        return $this->notificationReceiver->isAuthenticated(
            $payload,
            $merchantAccount,
            $this->configHelper->getNotificationsUsername(),
            $this->configHelper->getNotificationsPassword()
        );
    }

    public function validate(array $payload): bool
    {
        // Validate IP
        $ip = explode(',', (string) $this->remoteAddress->getRemoteAddress());
        if (!$this->ipAddressHelper->isIpAddressValid($ip)) {
            $this->adyenLogger->addAdyenNotification("Invalid IP for notification", $payload);
            return false;
        }

        // Validate HMAC
        $hasHmac = $this->configHelper->getNotificationsHmacKey() &&
            $this->hmacSignature->isHmacSupportedEventCode($payload);
        if ($hasHmac && !$this->notificationReceiver->validateHmac($payload, $this->configHelper->getNotificationsHmacKey())) {
            $this->adyenLogger->addAdyenNotification("HMAC validation failed", $payload);
            return false;
        }

        // Check duplicate
        $notification = $this->notificationFactory->create();
        $notification->setPspreference(trim((string) $payload['pspReference']));
        $notification->setEventCode(trim((string) $payload['eventCode']));
        $notification->setSuccess(trim((string) $payload['success'] ?? 'false'));
        $notification->setOriginalReference($payload['originalReference'] ?? null);

        return !$notification->isDuplicate();
    }

    public function toNotification(array $payload, string $notificationMode): Notification
    {
        $notification = $this->notificationFactory->create();

        $notification->setPspreference($payload['pspReference'] ?? null);
        $notification->setOriginalReference($payload['originalReference'] ?? null);
        $notification->setMerchantReference($payload['merchantReference'] ?? null);
        $notification->setEventCode($payload['eventCode'] ?? null);
        $notification->setSuccess($payload['success'] ?? null);
        $notification->setPaymentMethod($payload['paymentMethod'] ?? null);
        $notification->setReason($payload['reason'] ?? null);
        $notification->setDone($payload['done'] ?? null);
        $notification->setLive($notificationMode);

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

        return $notification;
    }
}
