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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\Order\Payment\PaymentRepository;
use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Helper\Webhook;
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Serialize\SerializerInterface;

class TokenWebhookAcceptor implements WebhookAcceptorInterface
{
    private const REQUIRED_FIELDS = [
        'environment',
        'eventId',
        'type',
        'data',
        'data.storedPaymentMethodId',
        'data.type',
        'data.shopperReference',
        'data.merchantAccount'
    ];

    /**
     * @param NotificationFactory $notificationFactory
     * @param AdyenLogger $adyenLogger
     * @param Webhook $webhookHelper
     * @param Config $configHelper
     * @param NotificationReceiver $notificationReceiver
     * @param PaymentRepository $paymentRepository
     * @param SerializerInterface $serializer
     * @param Http $request
     */
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly Webhook $webhookHelper,
        private readonly Config $configHelper,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly PaymentRepository $paymentRepository,
        private readonly SerializerInterface $serializer,
        private readonly Http $request
    ) { }

    /**
     * @throws AlreadyExistsException
     * @throws InvalidDataException
     * @throws AuthenticationException
     */
    public function getNotifications(array $payload): array
    {
        $order = null;

        if (!isset($payload['eventId'])) {
            $this->adyenLogger->addAdyenNotification('Missing required field [eventId] in token webhook', $payload);
            throw new InvalidDataException();
        }

        try {
            $payment = $this->paymentRepository->getPaymentByCcTransId($payload['eventId']);
            $order = $payment?->getOrder();
        } catch (\Throwable $e) {
            $this->adyenLogger->addAdyenNotification(
                sprintf('Could not load payment for reference %s: %s', $payload['eventId'], $e->getMessage()),
                $payload
            );
        }

        $isLive = $payload['environment'] === 'live' ? 'true' : 'false';
        $this->validate($payload, $isLive, $order);
        return [$this->toNotification($payload, $isLive, $order)];
    }

    /**
     * Validates the webhook environment mode, the required fields and the webhook merchantAccount
     *
     * @throws InvalidDataException
     * @throws AuthenticationException
     */
    private function validate(array $payload, $isLive, $order): void
    {
        foreach (self::REQUIRED_FIELDS as $fieldPath) {
            if (!$this->getNestedValue($payload, explode('.', $fieldPath))) {
                $this->adyenLogger->addAdyenNotification("Missing required field [$fieldPath] in token webhook", $payload);
                throw new InvalidDataException();
            }
        }

        if (!$this->notificationReceiver->validateNotificationMode($isLive, $this->configHelper->isDemoMode())) {
            $this->adyenLogger->addAdyenNotification("Invalid environment for the webhook!", $payload);
            throw new InvalidDataException();
        }

        $incomingMerchantAccount = $payload['data']['merchantAccount'];

        // TODO: Skip merchantAccount validation for `recurring.token.disabled` token webhook notifications.
        // Reason: The token-lifecycle `recurring.token.disabled` payload does not include the original payment PSP reference
        // or any order/payment association. Without that, we cannot reliably resolve the store scope to validate the
        // incoming merchantAccount against the store configuration. Therefore, we temporarily skip merchantAccount validation
        // for this specific event type.

        if (strcmp($payload['type'], Notification::RECURRING_TOKEN_DISABLED) !== 0) {
            if (!$this->webhookHelper->isMerchantAccountValid($incomingMerchantAccount, $payload, $order)) {
                $this->adyenLogger->addAdyenNotification(
                    "Merchant account mismatch while handling the webhook!",
                    $payload
                );
                throw new InvalidDataException();
            }
        }

        $webhookHmacKey = $this->configHelper->getNotificationsHmacKey();

        if ($webhookHmacKey) {
            $webhookHmacSignature = $this->request->getHeader('hmacsignature');
            $expectedSignature = base64_encode(
                hash_hmac('sha256', json_encode($payload), pack("H*", $webhookHmacKey), true)
            );

            if (strcmp($expectedSignature, $webhookHmacSignature) !== 0) {
                $this->adyenLogger->addAdyenNotification("HMAC validation failed", $payload);
                throw new AuthenticationException();
            }
        }
    }

    /**
     * @throws AlreadyExistsException
     */
    private function toNotification(array $payload, string $isLive, $order): Notification
    {
        $notification = $this->notificationFactory->create();

        $pspReference = $payload['data']['storedPaymentMethodId'];
        $originalReference = $payload['eventId'];

        if (isset($order)) {
            $notification->setMerchantReference($order->getIncrementId());
        }

        $notification->setPspreference($pspReference);
        $notification->setOriginalReference($originalReference);
        $notification->setEventCode($payload['type']);
        $notification->setLive($isLive);
        $notification->setSuccess('true');
        $notification->setPaymentMethod($payload['data']['type']);

        $additionalData = [
            'shopperReference' => $payload['data']['shopperReference']
        ];
        $notification->setAdditionalData($this->serializer->serialize($additionalData));

        $formattedDate = date('Y-m-d H:i:s');
        $notification->setCreatedAt($formattedDate);
        $notification->setUpdatedAt($formattedDate);

        // 💡 Add duplicate check here
        if ($notification->isDuplicate()) {
            throw new AlreadyExistsException(__('Webhook already exists!'));
        }

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
