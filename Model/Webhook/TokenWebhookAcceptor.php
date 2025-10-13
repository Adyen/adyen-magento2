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
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;
use Throwable;

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

    /** @var Order|null */
    private ?Order $order = null;

    /**
     * @param NotificationFactory $notificationFactory
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param NotificationReceiver $notificationReceiver
     * @param PaymentRepository $paymentRepository
     * @param SerializerInterface $serializer
     * @param Http $request
     */
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly PaymentRepository $paymentRepository,
        private readonly SerializerInterface $serializer,
        private readonly Http $request
    ) { }

    /**
     * @throws InvalidDataException
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    public function getNotifications(array $payload): array
    {
        $this->validate($payload);
        return [$this->toNotification($payload)];
    }

    /**
     * Validates the webhook environment mode, the required fields and the webhook merchantAccount
     *
     * @throws InvalidDataException
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    private function validate(array $payload): void
    {
        foreach (self::REQUIRED_FIELDS as $fieldPath) {
            if (!$this->getNestedValue($payload, explode('.', $fieldPath))) {
                throw new InvalidDataException(__(
                    'Missing required field `%1` in token webhook with originalReference %2',
                    $fieldPath,
                    $payload['eventId'] ?? null
                ));
            }
        }

        $storeId = null;

        try {
            $payment = $this->paymentRepository->getPaymentByCcTransId($payload['eventId']);
            $this->order = $payment?->getOrder();
            $storeId = $this->order?->getStoreId();
        } catch (Throwable $e) {
            $this->adyenLogger->addAdyenNotification(
                sprintf('Could not load payment for reference %s: %s', $payload['eventId'], $e->getMessage())
            );
        }

        $isLive = $payload['environment'] === 'live' ? 'true' : 'false';
        $isModeValid = $this->notificationReceiver->validateNotificationMode(
            $isLive,
            $this->configHelper->isDemoMode($storeId)
        );

        if (!$isModeValid) {
            $message = __('Mismatch between Live/Test modes of Magento store and the Adyen platform');

            $this->adyenLogger->addAdyenNotification($message, [
                'originalReference' => $payload['eventId'],
                'pspReference' => $payload['data']['storedPaymentMethodId']
            ]);

            throw new LocalizedException($message);
        }

        $webhookHmacKey = $this->configHelper->getNotificationsHmacKey($storeId);

        if ($webhookHmacKey) {
            $webhookHmacSignature = $this->request->getHeader('hmacsignature');
            $expectedSignature = base64_encode(
                hash_hmac('sha256', json_encode($payload), pack("H*", $webhookHmacKey), true)
            );

            if (strcmp($expectedSignature, $webhookHmacSignature) !== 0) {
                $this->adyenLogger->addAdyenNotification("HMAC validation failed", [
                    'originalReference' => $payload['eventId']
                ]);

                throw new AuthenticationException();
            }
        }
    }

    /**
     * @param array $payload
     * @return Notification
     */
    private function toNotification(array $payload): Notification
    {
        $notification = $this->notificationFactory->create();

        $pspReference = $payload['data']['storedPaymentMethodId'];
        $originalReference = $payload['eventId'];
        $isLive = $payload['environment'] === 'live' ? 'true' : 'false';

        if ($this->order) {
            $notification->setMerchantReference($this->order->getIncrementId());
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

        return $notification;
    }

    /**
     * @param array $array
     * @param array $path
     * @return mixed
     */
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
