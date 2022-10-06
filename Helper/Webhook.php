<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook\WebhookHandlerFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Notification as WebhookNotification;
use Adyen\Webhook\PaymentStates;
use Adyen\Webhook\Processor\ProcessorFactory;
use DateTime;
use Exception;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
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
        Order::STATE_CLOSED => PaymentStates::STATE_REFUNDED
    ];

    /**
     * Indicative matrix for possible states to enter after given event
     */
    const STATE_TRANSITION_MATRIX = [
        'payment_pre_authorized' => [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT],
        'payment_authorized' => [Order::STATE_PROCESSING]
    ];

    /**
     * @var AdyenLogger
     */
    private $logger;

    /** @var OrderHelper */
    private $orderHelper;

    /** @var OrderRepository */
    private $orderRepository;

    /**
     * @var Data
     */
    private $adyenHelper;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var TimezoneInterface
     */
    private $timezone;
    /**
     * @var ConfigHelper
     */
    private $configHelper;
    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    private $boletoPaidAmount;

    private $klarnaReservationNumber;

    private $ratepayDescriptor;

    /** @var WebhookHandlerFactory */
    private static $webhookHandlerFactory;

    public function __construct(
        Data $adyenHelper,
        SerializerInterface $serializer,
        TimezoneInterface $timezone,
        ConfigHelper $configHelper,
        ChargedCurrency $chargedCurrency,
        AdyenLogger $logger,
        WebhookHandlerFactory $webhookHandlerFactory,
        OrderHelper $orderHelper,
        OrderRepository $orderRepository
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->serializer = $serializer;
        $this->timezone = $timezone;
        $this->configHelper = $configHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
        $this->orderRepository = $orderRepository;
        self::$webhookHandlerFactory = $webhookHandlerFactory;
    }

    /**
     * @throws Exception
     */
    public function processNotification(Notification $notification): bool
    {
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

        // log the executed notification
        $order = $this->orderHelper->getOrderByIncrementId($notification->getMerchantReference());
        if (!$order) {
            $this->logger->addAdyenNotification(
                sprintf('Order w/merchant reference %s not found', $notification->getMerchantReference()),
            );

            return false;
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

            try {
                $webhookHandler = self::$webhookHandlerFactory::create($notification->getEventCode());
                $order = $webhookHandler->handleWebhook($order, $notification, $transitionState);
                $this->orderRepository->save($order);
            } catch (Exception $e) {
                $this->logger->addAdyenWarning($e->getMessage());
            }

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
             */
            $this->updateNotification($notification, false, true);
            $this->handleNotificationError($order, $notification, sprintf("Unsupported webhook notification: %s", $notification->getEventCode()));
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
        } catch (Exception $e) {
            $this->updateNotification($notification, false, false);
            $this->handleNotificationError($order, $notification, $e->getMessage());
            $this->logger->addAdyenNotification(
                sprintf(
                    "Notification %s had an error: %s \n %s",
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

    /**
     * @param Notification $notification
     * @param $currentOrderState
     * @return string
     * @throws InvalidDataException
     */
    private function getTransitionState(Notification $notification, $currentOrderState): string
    {
        $webhookNotificationItem = WebhookNotification::createItem([
            'eventCode' => $notification->getEventCode(),
            'success' => $notification->getSuccess(),
            'additionalData' => !empty($notification->getAdditionalData())
                ? $this->serializer->unserialize($notification->getAdditionalData()) : null,
        ]);
        $processor = ProcessorFactory::create($webhookNotificationItem, $currentOrderState, $this->logger);

        return $processor->process();
    }

    /**
     * @param Notification $notification
     * @param $processing
     * @param $done
     */
    private function updateNotification(Notification $notification, $processing, $done)
    {
        if ($done) {
            $notification->setDone(true);
        }
        $notification->setProcessing($processing);
        $notification->setUpdatedAt(new DateTime());
        $notification->save();
    }

    /**
     * Declare private variables for processing notification
     *
     * @param Notification $notification
     * @return void
     */
    private function declareVariables(Notification $notification)
    {
        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : "";

        if (is_array($additionalData)) {
            $additionalData2 = $additionalData['additionalData'] ?? null;
            if ($additionalData2 && is_array($additionalData2)) {
                $this->klarnaReservationNumber = isset($additionalData2['acquirerReference']) ? trim(
                    $additionalData2['acquirerReference']
                ) : "";
            }
            $ratepayDescriptor = $additionalData['openinvoicedata.descriptor'] ?? "";
            if ($ratepayDescriptor !== "") {
                $this->ratepayDescriptor = $ratepayDescriptor;
            }
        }
    }

    /**
     * @desc order comments or history
     */
    private function addNotificationDetailsHistoryComment(Order $order, Notification $notification): Order
    {
        $successResult = $notification->isSuccessful() ? 'true' : 'false';
        $reason = $notification->getReason();
        $success = (!empty($reason)) ? "$successResult <br />reason:$reason" : $successResult;

        $eventCode = $notification->getEventCode();
        if ($eventCode == Notification::REFUND || $eventCode == Notification::CAPTURE) {
            // check if it is a full or partial refund
            $amount = $notification->getAmountValue();
            $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
            $formattedOrderAmount = $this->adyenHelper
                ->formatAmount($orderAmountCurrency->getAmount(), $orderAmountCurrency->getCurrencyCode());

            if ($amount == $formattedOrderAmount) {
                $order->setData(
                    'adyen_notification_event_code',
                    $eventCode . " : " . strtoupper($successResult)
                );
            } else {
                $order->setData(
                    'adyen_notification_event_code',
                    "(PARTIAL) " .
                    $eventCode . " : " . strtoupper($successResult)
                );
            }
        } else {
            $order->setData(
                'adyen_notification_event_code',
                $eventCode . " : " . strtoupper($successResult)
            );
        }

        // if payment method is klarna, ratepay or openinvoice/afterpay show the reservartion number
        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
            $notification->getPaymentMethod()
        ) && !empty($this->klarnaReservationNumber)) {
            $klarnaReservationNumberText = "<br /> reservationNumber: " . $this->klarnaReservationNumber;
        } else {
            $klarnaReservationNumberText = "";
        }

        if ($this->boletoPaidAmount != null && $this->boletoPaidAmount != "") {
            $boletoPaidAmountText = "<br /> Paid amount: " . $this->boletoPaidAmount;
        } else {
            $boletoPaidAmountText = "";
        }

        $type = 'Adyen HTTP Notification(s):';
        $comment = __(
            '%1 <br /> eventCode: %2 <br /> pspReference: %3 <br /> paymentMethod: %4 <br />' .
            ' success: %5 %6 %7',
            $type,
            $eventCode,
            $notification->getPspreference(),
            $notification->getPaymentMethod(),
            $success,
            $klarnaReservationNumberText,
            $boletoPaidAmountText
        );

        // If notification is pending status and pending status is set add the status change to the comment history
        if ($eventCode == Notification::PENDING) {
            $pendingStatus = $this->configHelper->getConfigData(
                'pending_status',
                'adyen_abstract',
                $order->getStoreId()
            );
            if ($pendingStatus != "") {
                $order->addStatusHistoryComment($comment, $pendingStatus);
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

        $order->addStatusHistoryComment($comment, $order->getStatus());
        $this->logger->addAdyenNotification(
            'Created comment history for this notification',
            [
                'pspReference' => $notification->getPspReference(),
                'merchantReference' => $notification->getMerchantReference()
            ]
        );

        return $order;
    }

    /**
     * @param Notification $notification
     */
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
        ) : "";

        if ($notification->getEventCode() == Notification::AUTHORISATION
            || $notification->getEventCode() == Notification::HANDLED_EXTERNALLY
        ) {
            /*
             * if current notification is authorisation : false and
             * the  previous notification was authorisation : true do not update pspreference
             */
            if (!$notification->isSuccessful()) {
                $previousAdyenEventCode = $order->getData('adyen_notification_event_code');
                if ($previousAdyenEventCode != "AUTHORISATION : TRUE") {
                    $this->updateOrderPaymentWithAdyenAttributes($order->getPayment(), $notification, $additionalData);
                }
            } else {
                $this->updateOrderPaymentWithAdyenAttributes($order->getPayment(), $notification, $additionalData);
            }
        }

        return $order;
    }

    /**
     * TODO: Move this function or refactor or both
     */
    private function updateOrderPaymentWithAdyenAttributes(Order\Payment $payment, Notification $notification, array $additionalData): void
    {
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
        if (isset($additionalData['cardBin'])) {
            $payment->setAdditionalInformation('adyen_card_bin', $additionalData['cardBin']);
        }
        if (isset($additionalData['expiryDate'])) {
            $payment->setAdditionalInformation('adyen_expiry_date', $additionalData['expiryDate']);
        }
        if (isset($additionalData['issuerCountry'])) {
            $payment
                ->setAdditionalInformation('adyen_issuer_country', $additionalData['issuerCountry']);
        }
        $payment->setAdyenPspReference($notification->getPspreference());
        $payment->setAdditionalInformation('pspReference', $notification->getPspreference());

        if ($this->klarnaReservationNumber != "") {
            $payment->setAdditionalInformation(
                'adyen_klarna_number',
                $this->klarnaReservationNumber
            );
        }

        if ($this->boletoPaidAmount != "") {
            $payment->setAdditionalInformation('adyen_boleto_paid_amount', $this->boletoPaidAmount);
        }

        if ($this->ratepayDescriptor !== "") {
            $payment->setAdditionalInformation(
                'adyen_ratepay_descriptor',
                $this->ratepayDescriptor
            );
        }
    }

    /**
     * retrieve last 4 digits of card from the reason field
     *
     * @param $reason
     * @return string
     */
    private function retrieveLast4DigitsFromReason($reason)
    {
        $result = "";

        if ($reason != "") {
            $reasonArray = explode(":", $reason);
            if ($reasonArray != null && is_array($reasonArray) && isset($reasonArray[1])) {
                $result = $reasonArray[1];
            }
        }
        return $result;
    }

    /**
     * Add/update info on notification processing errors
     *
     */
    private function handleNotificationError(Order $order, Notification $notification, string $errorMessage): void
    {
        $this->setNotificationError($notification, $errorMessage);
        $this->addNotificationErrorComment($order, $errorMessage);
    }

    /**
     * Increases error count and appends error message to notification
     *
     * @param Notification $notification
     * @param string $errorMessage
     * @return void
     */
    private function setNotificationError(Notification $notification, string $errorMessage)
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

        $notification->save();
    }

    /**
     * Adds a comment to the order history with the notification processing error
     *
     */
    private function addNotificationErrorComment(Order $order, string $errorMessage): Order
    {
        $comment = __('The order failed to update: %1', $errorMessage);
        $order->addStatusHistoryComment($comment, $order->getStatus());
        $this->orderRepository->save($order);

        return $order;
    }
}
