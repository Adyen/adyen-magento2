<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Exception\AdyenWebhookException;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook\WebhookHandlerFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Config\Source\PreAuthorized;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Sales\Order\Payment\PaymentRepository;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Notification as WebhookNotification;
use Adyen\Webhook\PaymentStates;
use Adyen\Webhook\Processor\ProcessorFactory;
use Exception;
use Adyen\Payment\Model\Notification as NotificationEntity;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;

class Webhook
{
    const WEBHOOK_ORDER_STATE_MAPPING = [
        Order::STATE_NEW => PaymentStates::STATE_NEW,
        Order::STATE_PENDING_PAYMENT => PaymentStates::STATE_PENDING,
        Order::STATE_PAYMENT_REVIEW => PaymentStates::STATE_PENDING,
        Order::STATE_PROCESSING => PaymentStates::STATE_IN_PROGRESS,
        Order::STATE_COMPLETE => PaymentStates::STATE_PAID,
        Order::STATE_CANCELED => PaymentStates::STATE_CANCELLED,
        Order::STATE_CLOSED => PaymentStates::STATE_REFUNDED,
        NotificationEntity::STATE_ADYEN_AUTHORIZED => PaymentStates::STATE_PENDING
    ];

    /**
     * Indicative matrix for possible states to enter after given event
     */
    const STATE_TRANSITION_MATRIX = [
        'payment_pre_authorized' => [Order::STATE_NEW, PreAuthorized::STATE_ADYEN_AUTHORIZED],
        'payment_authorized' => [Order::STATE_PROCESSING]
    ];

    private ?string $klarnaReservationNumber;
    private ?string $ratepayDescriptor;

    /**
     * @param SerializerInterface $serializer
     * @param TimezoneInterface $timezone
     * @param Config $configHelper
     * @param AdyenLogger $logger
     * @param WebhookHandlerFactory $webhookHandlerFactory
     * @param OrderHelper $orderHelper
     * @param OrderRepository $orderRepository
     * @param OrderStatusHistory $orderStatusHistoryHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param AdyenNotificationRepositoryInterface $notificationRepository
     * @param IpAddress $ipAddressHelper
     * @param RemoteAddress $remoteAddress
     * @param OrderFactory $orderFactory
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly TimezoneInterface $timezone,
        private readonly ConfigHelper $configHelper,
        private readonly AdyenLogger $logger,
        private readonly WebhookHandlerFactory $webhookHandlerFactory,
        private readonly OrderHelper $orderHelper,
        private readonly OrderRepository $orderRepository,
        private readonly OrderStatusHistory $orderStatusHistoryHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly AdyenNotificationRepositoryInterface $notificationRepository,
        private readonly IpAddress $ipAddressHelper,
        private readonly RemoteAddress $remoteAddress,
        private readonly OrderFactory $orderFactory,
        private readonly PaymentRepository $paymentRepository
    ) {
        $this->klarnaReservationNumber = null;
        $this->ratepayDescriptor = null;
    }

    /**
     * @throws Exception
     */
    public function processNotification(Notification $notification): bool
    {
        if (strcmp($notification->getEventCode(), Notification::RECURRING_TOKEN_DISABLED) === 0) {
            return $this->processRecurringTokenDisabledWebhook($notification);
        }

        // check if merchant reference is set
        if (is_null($notification->getMerchantReference())) {
            $errorMessage = sprintf(
                'Invalid merchant reference for notification with the event code %s',
                $notification->getEventCode()
            );

            $this->logger->addAdyenNotification($errorMessage);

            $this->updateNotification($notification, false, true);
            $this->setNotificationError($notification, $errorMessage);

            return false;
        }

        // set notification processing to true
        $this->updateNotification($notification, true, false);
        $this->logger
            ->addAdyenNotification(
                sprintf(
                    "Processing %s notification %s",
                    $notification->getEventCode(),
                    $notification->getEntityId(),
                ), [
                    'merchantReference' => $notification->getMerchantReference(),
                    'pspReference' => $notification->getPspreference()
                ],
            );

        $order = $this->orderHelper->getOrderByIncrementId($notification->getMerchantReference());
        if (!$order instanceof OrderInterface) {
            $errorMessage = sprintf(
                'Order w/merchant reference %s not found',
                $notification->getMerchantReference()
            );

            $this->logger->addAdyenNotification($errorMessage);

            $this->updateNotification($notification, false, true);
            $this->setNotificationError($notification, $errorMessage);

            return false;
        }

        $payment = $order->getPayment();
        if ($payment instanceof OrderPaymentInterface) {
            $isAdyenPaymentMethod = $this->paymentMethodsHelper->isAdyenPayment($payment->getMethod());

            if (!$isAdyenPaymentMethod) {
                $errorMessage = sprintf(
                    'Invalid order payment method "%s" for notification with the event code %s (id %s)',
                    $payment->getMethod(),
                    $notification->getEventCode(),
                    $notification->getEntityId(),
                );

                $this->logger->addAdyenNotification($errorMessage);
                $this->updateNotification($notification, false, true);
                $this->setNotificationError($notification, $errorMessage);

                return false;
            }
        }

        try {
            // declare all variables that are needed
            $this->declareVariables($notification);

            // add notification to comment history status is current status
            $order = $this->addNotificationDetailsHistoryComment($order, $notification);

            // update order details
            $order = $this->updateAdyenAttributes($order, $notification);

            $order->getPayment()->setAdditionalInformation('payment_method', $notification->getPaymentMethod());

            // Get transition state
            $currentState = $this->getCurrentState($order->getState());
            if (!$currentState) {
                $this->logger->addAdyenNotification(
                    "ERROR: Unhandled order state '{orderState}'",
                    array_merge(
                        $this->logger->getOrderContext($order),
                        ['pspReference' => $notification->getPspreference()]
                    )
                );
                return false;
            }

            $transitionState = $this->getTransitionState($notification, $currentState);

            $webhookHandler = $this->webhookHandlerFactory->create($notification->getEventCode());
            $order = $webhookHandler->handleWebhook($order, $notification, $transitionState);
            $this->orderRepository->save($order);

            $this->updateNotification($notification, false, true);
            $this->logger->addAdyenNotification(
                sprintf("Notification %s was processed", $notification->getEntityId()),
                array_merge(
                    $this->logger->getOrderContext($order),
                    ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                )
            );

            return true;
        } catch (InvalidDataException $e) {
            /*
             * Webhook Module throws InvalidDataException if the eventCode is not supported.
             * Prevent re-process attempts and change the state of the notification to `done`.
             *
             * Same exception type is being thrown from the WebhookHandlerFactory
             * for webhook events that are not yet handled by the Adyen Magento plugin.
             */
            $this->updateNotification($notification, false, true);
            $this->handleNotificationError(
                $order,
                $notification,
                sprintf("Unsupported webhook notification: %s", $notification->getEventCode())
            );
            $this->logger->addAdyenNotification(
                sprintf(
                    "Notification %s had an error. Unsupported webhook notification: %s. %s",
                    $notification->getEntityId(),
                    $notification->getEventCode(),
                    $e->getMessage()
                ),
                array_merge(
                    $this->logger->getOrderContext($order),
                    ['pspReference' => $notification->getPspreference()]
                )
            );

            return false;
        } catch (AdyenWebhookException $e) {
            $this->updateNotification($notification, false, false);
            $this->handleNotificationError($order, $notification, $e->getMessage());
            $this->logger->addAdyenNotification(
                sprintf(
                    "Webhook notification error occurred. Notification %s had an error: %s \n %s",
                    $notification->getEntityId(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ),
                array_merge(
                    $this->logger->getOrderContext($order),
                    ['pspReference' => $notification->getPspReference()]
                )
            );
            return false;
        } catch (Exception $e) {
            $this->updateNotification($notification, false, false);
            $this->handleNotificationError($order, $notification, $e->getMessage());
            $this->logger->addAdyenNotification(
                sprintf(
                    "Critical error occurred. Notification %s had an error: %s \n %s",
                    $notification->getEntityId(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ),
                array_merge(
                    $this->logger->getOrderContext($order),
                    ['pspReference' => $notification->getPspReference()]
                )
            );
            return false;
        }
    }

    private function getCurrentState($orderState)
    {
        return self::WEBHOOK_ORDER_STATE_MAPPING[$orderState] ?? null;
    }

    private function getTransitionState(Notification $notification, $currentOrderState): string
    {
        $webhookNotificationItem = WebhookNotification::createItem([
            'eventCode' => $notification->getEventCode(),
            'success' => $notification->getSuccess(),
            'additionalData' => !empty($notification->getAdditionalData())
                ? $this->serializer->unserialize($notification->getAdditionalData()) : null,
        ]);
        $processor = ProcessorFactory::create($webhookNotificationItem, $currentOrderState);

        return $processor->process();
    }

    private function updateNotification(Notification $notification, $processing, $done): void
    {
        if ($done) {
            $notification->setDone(true);
        }
        $notification->setProcessing($processing);
        $notification->setUpdatedAt(date('Y-m-d H:i:s'));

        $this->notificationRepository->save($notification);
    }

    private function declareVariables(Notification $notification): void
    {
        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : [];

        if (!empty($additionalData)) {
            $additionalData2 = $additionalData['additionalData'] ?? null;
            if ($additionalData2 && is_array($additionalData2)) {
                $this->klarnaReservationNumber = isset($additionalData2['acquirerReference']) ? trim(
                    (string) $additionalData2['acquirerReference']
                ) : "";
            }
            $ratepayDescriptor = $additionalData['openinvoicedata.descriptor'] ?? "";
            if ($ratepayDescriptor !== "") {
                $this->ratepayDescriptor = $ratepayDescriptor;
            }
        }
    }

    private function addNotificationDetailsHistoryComment(Order $order, Notification $notification): Order
    {
        $comment = $this->orderStatusHistoryHelper->buildWebhookComment($order, $notification);

        // If notification is pending status and pending status is set add the status change to the comment history
        if ($notification->getEventCode() == Notification::PENDING) {
            $pendingStatus = $this->configHelper->getConfigData(
                'pending_status',
                'adyen_abstract',
                $order->getStoreId()
            );
            if ($pendingStatus != "") {
                $order->addCommentToStatusHistory($comment, $pendingStatus);
                $this->logger->addAdyenNotification(
                    'Created comment history for this notification with status change to: ' . $pendingStatus,
                    array_merge(
                        $this->logger->getOrderContext($order),
                        ['pspReference' => $notification->getPspreference()]
                    )
                );
                return $order;
            }
        }

        $order->addCommentToStatusHistory($comment, $order->getStatus());
        $this->logger->addAdyenNotification(
            'Created comment history for this notification',
            [
                'pspReference' => $notification->getPspReference(),
                'merchantReference' => $notification->getMerchantReference()
            ]
        );

        return $order;
    }

    private function updateAdyenAttributes(Order $order, Notification $notification): Order
    {
        $this->logger->addAdyenNotification(
            'Updating the Adyen attributes of the order',
            [
                'pspReference' => $notification->getPspreference(),
                'merchantReference' => $notification->getMerchantReference()
            ]
        );

        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : [];

        if ($notification->getEventCode() == Notification::AUTHORISATION
            || $notification->getEventCode() == Notification::HANDLED_EXTERNALLY
        ) {
            /*
             * if current notification is authorisation : false and
             * the  previous notification was authorisation : true do not update pspreference
             */
            if (!$notification->isSuccessful()) {
                $previousAdyenEventCode = $this->orderRepository
                    ->get($order->getId())
                    ->getData('adyen_notification_event_code');
                if ($previousAdyenEventCode != "AUTHORISATION : TRUE") {
                    $this->updateOrderPaymentWithAdyenAttributes($order->getPayment(), $notification, $additionalData);
                }
            } else {
                $this->updateOrderPaymentWithAdyenAttributes($order->getPayment(), $notification, $additionalData);
            }
        }

        return $order;
    }

    private function updateOrderPaymentWithAdyenAttributes(
        Order\Payment $payment,
        Notification $notification,
        array $additionalData
    ): void {
        if (isset($additionalData['avsResult'])) {
            $payment->setAdditionalInformation('adyen_avs_result', $additionalData['avsResult']);
        }
        if ((isset($additionalData['cvcResult']))) {
            $payment->setAdditionalInformation('adyen_cvc_result', $additionalData['cvcResult']);
        }
        if (isset($additionalData['totalFraudScore'])) {
            $payment->setAdditionalInformation('adyen_total_fraud_score', $additionalData['totalFraudScore']);
        }
        // if there is no server communication setup try to get last4 digits from reason field
        if (isset($additionalData['cardSummary'])) {
            $payment->setccLast4($additionalData['cardSummary']);
        } else {
            $payment->setccLast4($this->retrieveLast4DigitsFromReason($notification->getReason()));
        }
        if (isset($additionalData['refusalReasonRaw'])) {
            $payment->setAdditionalInformation('adyen_refusal_reason_raw', $additionalData['refusalReasonRaw']);
        }
        if (isset($additionalData['acquirerReference'])) {
            $payment->setAdditionalInformation('adyen_acquirer_reference', $additionalData['acquirerReference']);
        }
        if (isset($additionalData['authCode'])) {
            $payment->setAdditionalInformation('adyen_auth_code', $additionalData['authCode']);
        }
        $payment->setAdyenPspReference($notification->getPspreference());
        $payment->setAdditionalInformation('pspReference', $notification->getPspreference());

        if (!empty($this->ratepayDescriptor)) {
            $payment->setAdditionalInformation(
                'adyen_ratepay_descriptor',
                $this->ratepayDescriptor
            );
        }
    }

    private function retrieveLast4DigitsFromReason($reason): string
    {
        $result = "";

        if ($reason != "") {
            $reasonArray = explode(":", (string) $reason);
            if ($reasonArray != null && is_array($reasonArray) && isset($reasonArray[1])) {
                $result = $reasonArray[1];
            }
        }
        return $result;
    }

    private function handleNotificationError(Order $order, Notification $notification, string $errorMessage): void
    {
        $this->setNotificationError($notification, $errorMessage);
        $this->addNotificationErrorComment($order, $errorMessage);
    }

    private function setNotificationError(Notification $notification, string $errorMessage): void
    {
        $notification->setErrorCount($notification->getErrorCount() + 1);
        $oldMessage = $notification->getErrorMessage();
        $newMessage = sprintf(
            "[%s]: %s",
            $this->timezone->formatDateTime($notification->getUpdatedAt()),
            $errorMessage
        );
        if (empty($oldMessage)) {
            $notification->setErrorMessage($newMessage);
        } else {
            $notification->setErrorMessage($oldMessage . "\n" . $newMessage);
        }

        if ($notification->getErrorCount() === Notification::MAX_ERROR_COUNT) {
            $notification->setDone(true);
        }

        $this->notificationRepository->save($notification);
    }

    private function addNotificationErrorComment(Order $order, string $errorMessage): Order
    {
        $comment = __('The order failed to update: %1', $errorMessage);
        $order->addCommentToStatusHistory($comment, $order->getStatus());
        $this->orderRepository->save($order);
        return $order;
    }

    public function isIpValid(array $payload, string $context = 'webhook'): bool
    {
        $ip = explode(',', (string) $this->remoteAddress->getRemoteAddress());
        if (!$this->ipAddressHelper->isIpAddressValid($ip)) {
            $this->logger->addAdyenNotification("Invalid IP for $context", $payload);
            return false;
        }
        return true;
    }

    public function isMerchantAccountValid(string $incoming, array $payload, string $context = 'webhook'): bool
    {
        $originalReference = $payload['pspReference'] ?? $payload['data']['storedPaymentMethodId'] ?? null;
        $payment = null;

        if ($originalReference) {
            try {
                $payment = $this->paymentRepository->getPaymentByCcTransId($originalReference);
            } catch (\Throwable $e) {
                $this->logger->addAdyenNotification(
                    sprintf('Could not load payment for reference %s: %s', $originalReference, $e->getMessage()),
                    $payload
                );
            }
        }
        $storeId = $payment->getOrder()->getStoreId();

        $expected = $this->configHelper->getMerchantAccount($storeId);

        if ($expected === null) {
            $expected = $this->configHelper->getMotoMerchantAccounts($storeId);
        }

        $isValid = is_array($expected)
            ? in_array($incoming, $expected, true)
            : $incoming === $expected;

        if (!$isValid) {
            $expectedDisplay = is_array($expected) ? implode(', ', $expected) : (string)$expected;
            $this->logger->addAdyenNotification(
                "Merchant account mismatch for $context. Expected: $expectedDisplay, Received: $incoming",
                $payload
            );
            return false;
        }

        return true;
    }

    /**
     * Processes `recurring.token.disabled` webhook and returns the result
     *
     * This method needs to be introduced as the `recurring.token.disabled` webhook does not have
     * `merchantReference` or `originalPspReference` fields in its payload. Therefore, it's not possible
     * to create an association with any order. However, this webhook can be processed without having an
     * associated order or payment object.
     *
     * @param NotificationEntity $notification
     * @return bool
     */
    private function processRecurringTokenDisabledWebhook(NotificationEntity $notification): bool
    {
        try {
            $this->updateNotification($notification, true, false);
            $this->logger
                ->addAdyenNotification(
                    sprintf(
                        "Processing %s notification %s",
                        $notification->getEventCode(),
                        $notification->getEntityId(),
                    ),
                    ['pspReference' => $notification->getPspreference()],
                );

            $webhookHandler = $this->webhookHandlerFactory->create($notification->getEventCode());

            // Create an empty Order object to comply with the WebhookHandlerInterface::handleWebhook() method
            $order = $this->orderFactory->create();

            $webhookHandler->handleWebhook($order, $notification, '');

            $this->updateNotification($notification, false, true);
            $this->logger->addAdyenNotification(
                sprintf("Notification %s was processed", $notification->getEntityId()),
            );

            return true;
        } catch (Exception $e) {
            $this->updateNotification($notification, false, false);

            $this->logger->addAdyenNotification(
                sprintf(
                    "Critical error occurred. Notification %s had an error: %s \n %s",
                    $notification->getEntityId(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ),
                ['pspReference' => $notification->getPspReference()]
            );

            return false;
        }
    }
}
