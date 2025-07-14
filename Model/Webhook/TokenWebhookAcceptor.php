<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\AdyenException;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\Order\Payment\PaymentRepository;
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
        private readonly AdyenLogger $adyenLogger,
        private readonly Webhook $webhookHelper,
        private readonly PaymentRepository $paymentRepository
    ) {}

    public function validate(array $payload): bool
    {
        foreach (self::REQUIRED_FIELDS as $fieldPath) {
            if (!$this->getNestedValue($payload, explode('.', $fieldPath))) {
                $this->adyenLogger->addAdyenNotification("Missing required field [$fieldPath] in token webhook", $payload);
                return false;
            }
        }

        $incomingMerchantAccount = $payload['data']['merchantAccount'];
        return $this->webhookHelper->isMerchantAccountValid($incomingMerchantAccount, $payload, 'token webhook');
    }

    public function toNotificationList(array $payload): array
    {
        $isLive = $payload['environment'] === 'live' ? 'true' : 'false';

        return [$this->toNotification($payload, $isLive)];
    }

    /**
     * @throws AdyenException
     */
    private function toNotification(array $payload, string $isLive): Notification
    {
        $notification = $this->notificationFactory->create();

        $pspReference = $payload['data']['storedPaymentMethodId'];

        $payment = $this->paymentRepository->getPaymentByCcTransId($pspReference);
        if (empty($payment)) {
            throw new AdyenException(
                __("Order with pspReference %1 not found!", $pspReference)
            );
        }

        $notification->setMerchantReference($payment->getOrder()->getIncrementId());
        $notification->setPspreference($pspReference);
        $notification->setOriginalReference($payload['eventId']);
        $notification->setEventCode($payload['type']);
        $notification->setLive($isLive);
        $notification->setSuccess('true');
        $notification->setReason('Token lifecycle event');
        $notification->setPaymentMethod($payload['data']['type']);

        $formattedDate = date('Y-m-d H:i:s');
        $notification->setCreatedAt($formattedDate);
        $notification->setUpdatedAt($formattedDate);

        // 💡 Add duplicate check here
        if ($notification->isDuplicate()) {
            throw new \RuntimeException('Duplicate token notification');
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
