<?php

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Webhook\AuthorisationWebhookHandler;
use Adyen\Payment\Helper\Webhook\CancellationWebhookHandler;
use Adyen\Payment\Helper\Webhook\CancelOrRefundWebhookHandler;
use Adyen\Payment\Helper\Webhook\CaptureFailedWebhookHandler;
use Adyen\Payment\Helper\Webhook\CaptureWebhookHandler;
use Adyen\Payment\Helper\Webhook\ChargebackReversedWebhookHandler;
use Adyen\Payment\Helper\Webhook\ChargebackWebhookHandler;
use Adyen\Payment\Helper\Webhook\ManualReviewAcceptWebhookHandler;
use Adyen\Payment\Helper\Webhook\ManualReviewRejectWebhookHandler;
use Adyen\Payment\Helper\Webhook\NotificationOfChargebackWebhookHandler;
use Adyen\Payment\Helper\Webhook\OfferClosedWebhookHandler;
use Adyen\Payment\Helper\Webhook\OrderClosedWebhookHandler;
use Adyen\Payment\Helper\Webhook\OrderOpenedWebhookHandler;
use Adyen\Payment\Helper\Webhook\PendingWebhookHandler;
use Adyen\Payment\Helper\Webhook\RecurringContractWebhookHandler;
use Adyen\Payment\Helper\Webhook\RecurringTokenAlreadyExistingWebhookHandler;
use Adyen\Payment\Helper\Webhook\RecurringTokenCreatedWebhookHandler;
use Adyen\Payment\Helper\Webhook\RecurringTokenDisabledWebhookHandler;
use Adyen\Payment\Helper\Webhook\RefundFailedWebhookHandler;
use Adyen\Payment\Helper\Webhook\RefundWebhookHandler;
use Adyen\Payment\Helper\Webhook\RequestForInformationWebhookHandler;
use Adyen\Payment\Helper\Webhook\SecondChargebackWebhookHandler;
use Adyen\Payment\Helper\Webhook\WebhookHandlerFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class WebhookHandlerFactoryTest extends AbstractAdyenTestCase
{
    public function getNotificationsHandlersMap()
    {
        return [
          [Notification::HANDLED_EXTERNALLY, AuthorisationWebhookHandler::class],
            [Notification::AUTHORISATION, AuthorisationWebhookHandler::class],
            [Notification::CAPTURE, CaptureWebhookHandler::class],
            [Notification::OFFER_CLOSED, OfferClosedWebhookHandler::class],
            [Notification::REFUND, RefundWebhookHandler::class ],
            [Notification::REFUND_FAILED, RefundFailedWebhookHandler::class],
            [Notification::MANUAL_REVIEW_ACCEPT, ManualReviewAcceptWebhookHandler::class],
            [Notification::MANUAL_REVIEW_REJECT, ManualReviewRejectWebhookHandler::class],
            [Notification::RECURRING_CONTRACT, RecurringContractWebhookHandler::class],
            [Notification::PENDING, pendingWebhookHandler::class],
            [Notification::CANCELLATION, CancellationWebhookHandler::class],
            [Notification::CANCEL_OR_REFUND, CancelOrRefundWebhookHandler::class],
            [Notification::ORDER_CLOSED, OrderClosedWebhookHandler::class],
            [Notification::NOTIFICATION_OF_CHARGEBACK, NotificationOfChargebackWebhookHandler::class],
            [Notification::REQUEST_FOR_INFORMATION, RequestForInformationWebhookHandler::class],
            [Notification::CHARGEBACK_REVERSED, ChargebackReversedWebhookHandler::class],
            [Notification::CHARGEBACK, ChargebackWebhookHandler::class],
            [Notification::SECOND_CHARGEBACK, SecondChargebackWebhookHandler::class],
            [Notification::CAPTURE_FAILED, CaptureFailedWebhookHandler::class],
            [Notification::RECURRING_TOKEN_DISABLED, RecurringTokenDisabledWebhookHandler::class],
            [Notification::RECURRING_TOKEN_ALREADY_EXISTING, RecurringTokenAlreadyExistingWebhookHandler::class],
            [Notification::RECURRING_TOKEN_CREATED, RecurringTokenCreatedWebhookHandler::class]
        ];
    }

    /**
     * @dataProvider getNotificationsHandlersMap
     */
    public function testCreateHandler(string $notificationType, string $handlerType): void
    {
        $adyenLogger = $this->createMock(AdyenLogger::class);
        $authorisationWebhookHandler = $this->createMock(AuthorisationWebhookHandler::class);
        $captureWebhookHandler = $this->createMock(CaptureWebhookHandler::class);
        $offerClosedWebhookHandler = $this->createMock(OfferClosedWebhookHandler::class);
        $refundWebhookHandler = $this->createMock(RefundWebhookHandler::class);
        $refundFailedWebhookHandler = $this->createMock(RefundFailedWebhookHandler::class);
        $manualReviewAcceptWebhookHandler = $this->createMock(ManualReviewAcceptWebhookHandler::class);
        $manualReviewRejectWebhookHandler = $this->createMock(ManualReviewRejectWebhookHandler::class);
        $recurringContractWebhookHandler = $this->createMock(RecurringContractWebhookHandler::class);
        $pendingWebhookHandler = $this->createMock(PendingWebhookHandler::class);
        $cancellationWebhookHandler = $this->createMock(CancellationWebhookHandler::class);
        $cancelOrRefundWebhookHandler = $this->createMock(CancelOrRefundWebhookHandler::class);
        $orderClosedWebhookHandler = $this->createMock(OrderClosedWebhookHandler::class);
        $orderOpenedWebhookHandler = $this->createMock(OrderOpenedWebhookHandler::class);
        $chargebackWebhookHandler = $this->createMock(ChargebackWebhookHandler::class);
        $requestForInformationWebhookHandler = $this->createMock(RequestForInformationWebhookHandler::class);
        $chargebackReversedWebhookHandler = $this->createMock(ChargebackReversedWebhookHandler::class);
        $secondChargebackWebhookHandler = $this->createMock(SecondChargebackWebhookHandler::class);
        $notificationOfChargebackWebhookHandler = $this->createMock(NotificationOfChargebackWebhookHandler::class);
        $captureFailedWebhookHandler = $this->createMock(CaptureFailedWebhookHandler::class);
        $recurringTokenDisabledWebhookHandler = $this->createMock(RecurringTokenDisabledWebhookHandler::class);
        $recurringTokenAlreadyExistingWebhookHandler =
            $this->createMock(RecurringTokenAlreadyExistingWebhookHandler::class);
        $recurringTokenCreatedWebhookHandler = $this->createMock(RecurringTokenCreatedWebhookHandler::class);

        $factory = new WebhookHandlerFactory(
            $adyenLogger,
            $authorisationWebhookHandler,
            $captureWebhookHandler,
            $offerClosedWebhookHandler,
            $refundWebhookHandler,
            $refundFailedWebhookHandler,
            $manualReviewAcceptWebhookHandler,
            $manualReviewRejectWebhookHandler,
            $recurringContractWebhookHandler,
            $pendingWebhookHandler,
            $cancellationWebhookHandler,
            $cancelOrRefundWebhookHandler,
            $orderClosedWebhookHandler,
            $orderOpenedWebhookHandler,
            $chargebackWebhookHandler,
            $requestForInformationWebhookHandler,
            $chargebackReversedWebhookHandler,
            $secondChargebackWebhookHandler,
            $notificationOfChargebackWebhookHandler,
            $captureFailedWebhookHandler,
            $recurringTokenAlreadyExistingWebhookHandler,
            $recurringTokenDisabledWebhookHandler,
            $recurringTokenCreatedWebhookHandler
        );

        $handler = $factory->create($notificationType);
        $this->assertInstanceOf($handlerType, $handler);
    }
}
