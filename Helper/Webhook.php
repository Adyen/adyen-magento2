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

use Adyen\Payment\Helper\Order as AdyenOrderHelper;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Webhook\WebhookHandlerFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Notification as WebhookNotification;
use Adyen\Webhook\PaymentStates;
use Adyen\Webhook\Processor\ProcessorFactory;
use DateTime;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
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
     * @var Order
     */
    private $order;
    /**
     * @var AdyenLogger
     */
    private $logger;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var OrderRepository
     */
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

    /** @var AdyenOrderHelper */
    private $orderHelper;

    private $boletoPaidAmount;

    private $klarnaReservationNumber;

    private $ratepayDescriptor;

    /** @var WebhookHandlerFactory */
    private static $webhookHandlerFactory;

    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepository $orderRepository,
        Data $adyenHelper,
        SerializerInterface $serializer,
        TimezoneInterface $timezone,
        ConfigHelper $configHelper,
        ChargedCurrency $chargedCurrency,
        AdyenLogger $logger,
        WebhookHandlerFactory $webhookHandlerFactory,
        AdyenOrderHelper $orderHelper
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->adyenHelper = $adyenHelper;
        $this->serializer = $serializer;
        $this->timezone = $timezone;
        $this->configHelper = $configHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
        self::$webhookHandlerFactory = $webhookHandlerFactory;
    }

    /**
     * @param Notification $notification
     * @return bool
     */
    public function processNotification(Notification $notification): bool
    {
        $this->order = null;
        // set notification processing to true
        $this->updateNotification($notification, true, false);
        $this->logger
            ->addAdyenNotificationCronjob(sprintf("Processing notification %s", $notification->getEntityId()));

        try {
            // log the executed notification
            $this->logger->addAdyenNotificationCronjob(json_encode($notification->debug()));
            $this->setOrderByIncrementId($notification);
            if (!$this->order) {
                // order does not exists remove from queue
                $notification->delete();

                return false;
            }

            $this->logger->addAdyenNotificationCronjob(
                sprintf("Notification %s will be processed", $notification->getEntityId()),
                $this->orderHelper->getLogOrderContext($this->order)
            );

            // declare all variables that are needed
            $this->declareVariables($this->order, $notification);

            // add notification to comment history status is current status
            $this->addNotificationDetailsHistoryComment($this->order, $notification);

            // update order details
            $this->updateAdyenAttributes($notification);

            $this->order->getPayment()->setAdditionalInformation('payment_method', $notification->getPaymentMethod());

            // Get transition state
            $currentState = $this->getCurrentState($this->order->getState());
            if (!$currentState) {
                $this->logger->addAdyenNotificationCronjob(
                    sprintf("ERROR: Unhandled order state '%s'.", $this->order->getState())
                );
                return false;
            }

            $transitionState = $this->getTransitionState($notification, $currentState);

            try {
                $webhookHandler = self::$webhookHandlerFactory::create($notification->getEventCode());
                $this->order = $webhookHandler->handleWebhook($this->order, $notification, $transitionState);
                // set done to true
                $this->order->save();
            } catch (Exception $e) {
                $this->logger->addAdyenNotificationCronjob($e->getMessage());
            }

            $this->updateNotification($notification, false, true);
            $this->logger->addAdyenNotificationCronjob(
                sprintf("Notification %s was processed", $notification->getEntityId()),
                $this->orderHelper->getLogOrderContext($this->order)
            );

            return true;
        } catch (Exception $e) {
            $this->updateNotification($notification, false, false);
            $this->handleNotificationError($notification, $e->getMessage());
            $this->logger->addAdyenNotificationCronjob(
                sprintf(
                    "Notification %s had an error: %s \n %s",
                    $notification->getEntityId(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ),
                $this->orderHelper->getLogOrderContext($this->order)
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
     * @param Order $order
     * @param Notification $notification
     * @return void
     */
    private function declareVariables(Order $order, Notification $notification)
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
    private function addNotificationDetailsHistoryComment(Order $order, Notification $notification)
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

            $this->logger->addAdyenNotificationCronjob(
                'amount notification:' . $amount . ' amount order:' . $formattedOrderAmount
            );

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
                $this->logger->addAdyenNotificationCronjob(
                    'Created comment history for this notification with status change to: ' . $pendingStatus
                );
                return;
            }
        }

        $order->addStatusHistoryComment($comment, $order->getStatus());
        $this->logger->addAdyenNotificationCronjob('Created comment history for this notification');
    }

    /**
     * @param Notification $notification
     */
    private function updateAdyenAttributes(Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Updating the Adyen attributes of the order');

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
                $previousAdyenEventCode = $this->order->getData('adyen_notification_event_code');
                if ($previousAdyenEventCode != "AUTHORISATION : TRUE") {
                    $this->updateOrderPaymentWithAdyenAttributes($notification, $additionalData);
                }
            } else {
                $this->updateOrderPaymentWithAdyenAttributes($notification, $additionalData);
            }
        }
    }

    /**
     * @param Notification $notification
     * @param $additionalData
     */
    private function updateOrderPaymentWithAdyenAttributes(Notification $notification, $additionalData)
    {
        if (!is_array($additionalData)) {
            return;
        }
        if (isset($additionalData['avsResult'])) {
            $this->order->getPayment()->setAdditionalInformation('adyen_avs_result', $additionalData['avsResult']);
        }
        if ((isset($additionalData['cvcResult']))) {
            $this->order->getPayment()->setAdditionalInformation('adyen_cvc_result', $additionalData['cvcResult']);
        }
        if (isset($additionalData['totalFraudScore'])) {
            $this->order->getPayment()
                ->setAdditionalInformation('adyen_total_fraud_score', $additionalData['totalFraudScore']);
        }
        // if there is no server communication setup try to get last4 digits from reason field
        if (isset($additionalData['cardSummary'])) {
            $this->order->getPayment()->setccLast4($additionalData['cardSummary']);
        } else {
            $this->order->getPayment()->setccLast4($this->retrieveLast4DigitsFromReason($notification->getReason()));
        }
        if (isset($additionalData['refusalReasonRaw'])) {
            $this->order->getPayment()
                ->setAdditionalInformation('adyen_refusal_reason_raw', $additionalData['refusalReasonRaw']);
        }
        if (isset($additionalData['acquirerReference'])) {
            $this->order->getPayment()
                ->setAdditionalInformation('adyen_acquirer_reference', $additionalData['acquirerReference']);
        }
        if (isset($additionalData['authCode'])) {
            $this->order->getPayment()->setAdditionalInformation('adyen_auth_code', $additionalData['authCode']);
        }
        if (isset($additionalData['cardBin'])) {
            $this->order->getPayment()->setAdditionalInformation('adyen_card_bin', $additionalData['cardBin']);
        }
        if (isset($additionalData['expiryDate'])) {
            $this->order->getPayment()->setAdditionalInformation('adyen_expiry_date', $additionalData['expiryDate']);
        }
        if (isset($additionalData['issuerCountry'])) {
            $this->order->getPayment()
                ->setAdditionalInformation('adyen_issuer_country', $additionalData['issuerCountry']);
        }
        $this->order->getPayment()->setAdyenPspReference($notification->getPspreference());
        $this->order->getPayment()->setAdditionalInformation('pspReference', $notification->getPspreference());

        if ($this->klarnaReservationNumber != "") {
            $this->order->getPayment()->setAdditionalInformation(
                'adyen_klarna_number',
                $this->klarnaReservationNumber
            );
        }
        if ($this->boletoPaidAmount != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_boleto_paid_amount', $this->boletoPaidAmount);
        }
        if ($this->ratepayDescriptor !== "") {
            $this->order->getPayment()->setAdditionalInformation(
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
     * @param Notification $notification
     * @param string $errorMessage
     * @return void
     */
    private function handleNotificationError($notification, $errorMessage)
    {
        $this->setNotificationError($notification, $errorMessage);
        $this->addNotificationErrorComment($errorMessage);
    }

    /**
     * Increases error count and appends error message to notification
     *
     * @param Notification $notification
     * @param string $errorMessage
     * @return void
     */
    private function setNotificationError($notification, $errorMessage)
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
     * @param string $errorMessage
     * @return void
     */
    private function addNotificationErrorComment($errorMessage)
    {
        $comment = __('The order failed to update: %1', $errorMessage);
        if ($this->order) {
            $this->order->addStatusHistoryComment($comment, $this->order->getStatus());
            $this->order->save();
        }
    }

    /**
     * Set the order data member by fetching the entity from the database.
     * This should be moved out of this file in the future.
     * @param Notification $notification
     */
    private function setOrderByIncrementId(Notification $notification)
    {
        $incrementId = $notification->getMerchantReference();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();

        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

        /** @var Order $order */
        $order = reset($orderList);
        $this->order = $order;
    }
}
