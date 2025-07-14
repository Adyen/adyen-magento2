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

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\Exception\InvalidDataException;

class WebhookHandlerFactory
{
    private AuthorisationWebhookHandler $authorisationWebhookHandler;
    private CaptureWebhookHandler $captureWebhookHandler;
    private OfferClosedWebhookHandler $offerClosedWebhookHandler;
    private AdyenLogger $adyenLogger;
    private RefundWebhookHandler $refundWebhookHandler;
    private RefundFailedWebhookHandler $refundFailedWebhookHandler;
    private ManualReviewAcceptWebhookHandler $manualReviewAcceptWebhookHandler;
    private ManualReviewRejectWebhookHandler $manualReviewRejectWebhookHandler;
    private RecurringContractWebhookHandler $recurringContractWebhookHandler;
    private PendingWebhookHandler $pendingWebhookHandler;
    private CancellationWebhookHandler $cancellationWebhookHandler;
    private CancelOrRefundWebhookHandler $cancelOrRefundWebhookHandler;
    private OrderClosedWebhookHandler $orderClosedWebhookHandler;
    private OrderOpenedWebhookHandler $orderOpenedWebhookHandler;
    private ChargebackWebhookHandler $chargebackWebhookHandler;
    private ChargebackReversedWebhookHandler $chargebackReversedWebhookHandler;
    private NotificationOfChargebackWebhookHandler $notificationOfChargebackWebhookHandler;
    private RequestForInformationWebhookHandler $requestForInformationWebhookHandler;
    private SecondChargebackWebhookHandler $secondChargebackWebhookHandler;
    private CaptureFailedWebhookHandler $captureFailedWebhookHandler;
    private RecurringTokenAlreadyExistingWebhookHandler $recurringTokenAlreadyExistingWebhookHandler;

    public function __construct(
        AdyenLogger $adyenLogger,
        AuthorisationWebhookHandler $authorisationWebhookHandler,
        CaptureWebhookHandler $captureWebhookHandler,
        OfferClosedWebhookHandler $offerClosedWebhookHandler,
        RefundWebhookHandler $refundWebhookHandler,
        RefundFailedWebhookHandler $refundFailedWebhookHandler,
        ManualReviewAcceptWebhookHandler $manualReviewAcceptWebhookHandler,
        ManualReviewRejectWebhookHandler $manualReviewRejectWebhookHandler,
        RecurringContractWebhookHandler $recurringContractWebhookHandler,
        PendingWebhookHandler $pendingWebhookHandler,
        CancellationWebhookHandler $cancellationWebhookHandler,
        CancelOrRefundWebhookHandler $cancelOrRefundWebhookHandler,
        OrderClosedWebhookHandler $orderClosedWebhookHandler,
        OrderOpenedWebhookHandler $orderOpenedWebhookHandler,
        ChargebackWebhookHandler $chargebackWebhookHandler,
        RequestForInformationWebhookHandler $requestForInformationWebhookHandler,
        ChargebackReversedWebhookHandler $chargebackReversedWebhookHandler,
        SecondChargebackWebhookHandler $secondChargebackWebhookHandler,
        NotificationOfChargebackWebhookHandler $notificationOfChargebackWebhookHandler,
        CaptureFailedWebhookHandler $captureFailedWebhookHandler,
        RecurringTokenAlreadyExistingWebhookHandler  $recurringTokenAlreadyExistingWebhookHandler
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->authorisationWebhookHandler = $authorisationWebhookHandler;
        $this->captureWebhookHandler = $captureWebhookHandler;
        $this->offerClosedWebhookHandler = $offerClosedWebhookHandler;
        $this->refundWebhookHandler = $refundWebhookHandler;
        $this->refundFailedWebhookHandler = $refundFailedWebhookHandler;
        $this->manualReviewAcceptWebhookHandler = $manualReviewAcceptWebhookHandler;
        $this->manualReviewRejectWebhookHandler = $manualReviewRejectWebhookHandler;
        $this->recurringContractWebhookHandler = $recurringContractWebhookHandler;
        $this->pendingWebhookHandler = $pendingWebhookHandler;
        $this->cancellationWebhookHandler = $cancellationWebhookHandler;
        $this->cancelOrRefundWebhookHandler = $cancelOrRefundWebhookHandler;
        $this->orderClosedWebhookHandler = $orderClosedWebhookHandler;
        $this->orderOpenedWebhookHandler = $orderOpenedWebhookHandler;
        $this->chargebackWebhookHandler = $chargebackWebhookHandler;
        $this->requestForInformationWebhookHandler = $requestForInformationWebhookHandler;
        $this->chargebackReversedWebhookHandler = $chargebackReversedWebhookHandler;
        $this->secondChargebackWebhookHandler = $secondChargebackWebhookHandler;
        $this->notificationOfChargebackWebhookHandler = $notificationOfChargebackWebhookHandler;
        $this->captureFailedWebhookHandler = $captureFailedWebhookHandler;
        $this->recurringTokenAlreadyExistingWebhookHandler = $recurringTokenAlreadyExistingWebhookHandler;
    }

    /**
     * @throws InvalidDataException
     */
    public function create(string $eventCode): WebhookHandlerInterface
    {
        switch ($eventCode) {
            case Notification::HANDLED_EXTERNALLY:
            case Notification::AUTHORISATION:
                return $this->authorisationWebhookHandler;
            case Notification::CAPTURE:
                return $this->captureWebhookHandler;
            case Notification::OFFER_CLOSED:
                return $this->offerClosedWebhookHandler;
            case Notification::REFUND:
                return $this->refundWebhookHandler;
            case Notification::REFUND_FAILED:
                return $this->refundFailedWebhookHandler;
            case Notification::MANUAL_REVIEW_ACCEPT:
                return $this->manualReviewAcceptWebhookHandler;
            case Notification::MANUAL_REVIEW_REJECT:
                return $this->manualReviewRejectWebhookHandler;
            case Notification::RECURRING_CONTRACT:
                return $this->recurringContractWebhookHandler;
            case Notification::PENDING:
                return $this->pendingWebhookHandler;
            case Notification::CANCELLATION:
                return $this->cancellationWebhookHandler;
            case Notification::CANCEL_OR_REFUND:
                return $this->cancelOrRefundWebhookHandler;
            case Notification::ORDER_OPENED:
                return $this->orderOpenedWebhookHandler;
            case Notification::ORDER_CLOSED:
                return $this->orderClosedWebhookHandler;
            case Notification::CHARGEBACK:
                return $this->chargebackWebhookHandler;
            case Notification::NOTIFICATION_OF_CHARGEBACK:
                return $this->notificationOfChargebackWebhookHandler;
            case Notification::REQUEST_FOR_INFORMATION:
                return $this->requestForInformationWebhookHandler;
            case Notification::CHARGEBACK_REVERSED:
                return $this->chargebackReversedWebhookHandler;
            case Notification::SECOND_CHARGEBACK:
                return $this->secondChargebackWebhookHandler;
            case Notification::CAPTURE_FAILED:
                return $this->captureFailedWebhookHandler;
            case Notification::RECURRING_TOKEN_ALREADY_EXISTING:
                return $this->recurringTokenAlreadyExistingWebhookHandler;
        }

        $exceptionMessage = sprintf(
            'Unknown webhook type: %s. This type is not yet handled by the Adyen Magento plugin', $eventCode
        );

        $this->adyenLogger->addAdyenWarning($exceptionMessage);
        /*
         * InvalidDataException is used for consistency. Since Webhook Module
         * throws the same exception for unknown webhook event codes.
         */
        throw new InvalidDataException(__($exceptionMessage));
    }
}
