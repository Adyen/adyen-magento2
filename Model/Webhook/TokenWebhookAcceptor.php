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

use Adyen\AdyenException;
use Adyen\Payment\Exception\AuthenticationException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\Order\Payment\PaymentRepository;
use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Helper\Webhook;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\Exception\AlreadyExistsException;

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
     */
    public function __construct(
        private readonly NotificationFactory $notificationFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly Webhook $webhookHelper,
        private readonly Config $configHelper,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly PaymentRepository $paymentRepository
    ) { }

    public function getNotifications(array $payload): array
    {
        $this->validate($payload);

        $isLive = $payload['environment'] === 'live' ? 'true' : 'false';
        return [$this->toNotification($payload, $isLive)];
    }

    /**
     * Validates the webhook environment mode, the required fields and the webhook merchantAccount
     *
     * @throws InvalidDataException
     */
    private function validate(array $payload): void
    {
        foreach (self::REQUIRED_FIELDS as $fieldPath) {
            if (!$this->getNestedValue($payload, explode('.', $fieldPath))) {
                $this->adyenLogger->addAdyenNotification("Missing required field [$fieldPath] in token webhook", $payload);
                throw new InvalidDataException();
            }
        }

        $isLiveMode = $payload['environment'] === 'live' ? 'true' : 'false';

        if (!$this->notificationReceiver->validateNotificationMode($isLiveMode, $this->configHelper->isDemoMode())) {
            $this->adyenLogger->addAdyenNotification("Invalid environment for the webhook!", $payload);
            throw new InvalidDataException();
        }

        $incomingMerchantAccount = $payload['data']['merchantAccount'] ?? null;
        if (!$this->webhookHelper->isMerchantAccountValid($incomingMerchantAccount, $payload)) {
            $this->adyenLogger->addAdyenNotification(
                "Merchant account mismatch while handling the webhook!",
                $payload
            );
            throw new InvalidDataException();
        }
    }

    /**
     * @throws AdyenException
     * @throws AlreadyExistsException
     */
    private function toNotification(array $payload, string $isLive): Notification
    {
        $notification = $this->notificationFactory->create();

        $pspReference = $payload['data']['storedPaymentMethodId'];
        $originalReference = $payload['eventId'];

        $payment = $this->paymentRepository->getPaymentByCcTransId($originalReference);
        if (empty($payment)) {
            throw new AdyenException(
                __("Order with pspReference %1 not found while handling the webhook!", $originalReference)
            );
        }

        $notification->setMerchantReference($payment->getOrder()->getIncrementId());
        $notification->setPspreference($pspReference);
        $notification->setOriginalReference($originalReference);
        $notification->setEventCode($payload['type']);
        $notification->setLive($isLive);
        $notification->setSuccess('true');
        $notification->setPaymentMethod($payload['data']['type']);

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
