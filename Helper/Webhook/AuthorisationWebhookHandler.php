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

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;

class AuthorisationWebhookHandler implements WebhookHandlerInterface
{
    /**
     * @param AdyenOrderPayment $adyenOrderPaymentHelper
     * @param OrderHelper $orderHelper
     * @param CaseManagement $caseManagementHelper
     * @param SerializerInterface $serializer
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param Invoice $invoiceHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param CartRepositoryInterface $cartRepository
     * @param AdyenNotificationRepositoryInterface $notificationRepository
     * @param CleanupAdditionalInformationInterface $cleanupAdditionalInformation
     */
    public function __construct(
        private readonly AdyenOrderPayment $adyenOrderPaymentHelper,
        private readonly OrderHelper $orderHelper,
        private readonly CaseManagement $caseManagementHelper,
        private readonly SerializerInterface $serializer,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly Invoice $invoiceHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AdyenNotificationRepositoryInterface $notificationRepository,
        private readonly CleanupAdditionalInformationInterface $cleanupAdditionalInformation
    ) { }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param string $transitionState
     * @return Order
     * @throws LocalizedException
     */
    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order
    {
        if ($transitionState === PaymentStates::STATE_PAID) {
            $order = $this->handleSuccessfulAuthorisation($order, $notification);
        } elseif ($transitionState === PaymentStates::STATE_FAILED) {
            $order = $this->handleFailedAuthorisation($order, $notification);
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @return Order
     * @throws LocalizedException
     */
    private function handleSuccessfulAuthorisation(Order $order, Notification $notification): Order
    {
        $isAutoCapture = $this->paymentMethodsHelper->isAutoCapture($order, $notification->getPaymentMethod());

        // Set adyen_notification_payment_captured to true so that we ignore a possible OFFER_CLOSED
        if ($notification->isSuccessful() && $isAutoCapture) {
            $order->setData('adyen_notification_payment_captured', 1);
        }

        $this->adyenOrderPaymentHelper->createAdyenOrderPayment($order, $notification, $isAutoCapture);
        $isFullAmountAuthorized = $this->adyenOrderPaymentHelper->isFullAmountAuthorized($order);

        if ($isFullAmountAuthorized) {
            $order = $this->orderHelper->setPrePaymentAuthorized($order);
            $this->orderHelper->updatePaymentDetails($order, $notification);

            $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize($notification->getAdditionalData()) : [];
            $requireFraudManualReview = $this->caseManagementHelper->requiresManualReview($additionalData);

            if ($isAutoCapture) {
                $order = $this->handleAutoCapture($order, $notification, $requireFraudManualReview);
            } else {
                $order = $this->handleManualCapture($order, $notification, $requireFraudManualReview);
            }

            // For Boleto confirmation mail is sent on order creation
            // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
            if ($notification->getPaymentMethod() != "adyen_boleto" && !$order->getEmailSent()) {
                $this->orderHelper->sendOrderMail($order);
            }

            // Set authorized amount in sales_order_payment
            $order->getPayment()->setAmountAuthorized($order->getGrandTotal());
            $order->getPayment()->setBaseAmountAuthorized($order->getBaseGrandTotal());

            // Clean-up the data temporarily stored in `additional_information`
            $this->cleanupAdditionalInformation->execute($order->getPayment());
        } else {
            $this->orderHelper->addWebhookStatusHistoryComment($order, $notification);
        }

        if ($notification->getPaymentMethod() == "c_cash" &&
            $this->configHelper->getConfigData('create_shipment', 'adyen_cash', $order->getStoreId())
        ) {
            $this->orderHelper->createShipment($order);
        }

        // Disable the quote if it's still active
        $quote = $this->cartRepository->get($order->getQuoteId());
        if ($quote->getIsActive()) {
            $quote->setIsActive(false);
            $this->cartRepository->save($quote);
        }

        return $order;
    }

    /**
     * @throws LocalizedException
     */
    private function handleFailedAuthorisation(Order $order, Notification $notification): Order
    {
        $previousAdyenEventCode = $order->getData('adyen_notification_event_code');

        /*
         * Don't cancel the order if part of the payment has been captured.
         * Partial payments can fail, if the second payment has failed then the first payment is
         * refund/cancelled as well. So if it is a partial payment that failed cancel the order as well
         * TODO: Refactor this by using the adyenOrderPayment Table
         */
        $paymentPreviouslyCaptured = $order->getData('adyen_notification_payment_captured');

        if ($previousAdyenEventCode == "AUTHORISATION : TRUE" || !empty($paymentPreviouslyCaptured)) {
            $this->adyenLogger->addAdyenNotification(
                'Order is not cancelled because previous notification
                                    was an authorisation that succeeded and payment was captured',
                [
                    'pspReference' => $notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );

            return $order;
        }

        // Order is already Cancelled
        if ($order->isCanceled() || $order->getState() === Order::STATE_HOLDED) {
            $this->adyenLogger->addAdyenNotification(
                "Order is already cancelled or holded, do nothing",
                [
                    'pspReference' =>$notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );

            return $order;
        }

        // If the payment method is PBL, use failure counter before cancelling the order
        if ($order->getPayment()->getMethod() == AdyenPayByLinkConfigProvider::CODE) {
            if (!$this->canCancelPayByLinkOrder($order, $notification)) {
                return $order;
            }
        }

        // Move the order from PAYMENT_REVIEW to NEW, so that can be cancelled
        if (!$order->canCancel() && $this->configHelper->getNotificationsCanCancel($order->getStoreId())) {
            $order->setState(Order::STATE_NEW);
        }

        // Clean-up the data temporarily stored in `additional_information`
        $this->cleanupAdditionalInformation->execute($order->getPayment());

        return $this->orderHelper->holdCancelOrder($order, true);
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param bool $requireFraudManualReview
     * @return Order
     * @throws LocalizedException
     */
    private function handleAutoCapture(Order $order, Notification $notification, bool $requireFraudManualReview): Order
    {
        $this->invoiceHelper->createInvoice($order, $notification, true);
        if ($requireFraudManualReview) {
             $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $notification->getPspreference(), true);
        } else {
            $order = $this->orderHelper->finalizeOrder($order, $notification);
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param bool $requireFraudManualReview
     * @return Order
     */
    private function handleManualCapture(Order $order, Notification $notification, bool $requireFraudManualReview): Order
    {
        if ($requireFraudManualReview) {
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $notification->getPspreference(), false);
        } else {
            $order = $this->orderHelper->addWebhookStatusHistoryComment($order, $notification);
            $order->addStatusHistoryComment(__('Capture Mode set to Manual'), $order->getStatus());
            $this->adyenLogger->addAdyenNotification(
                'Capture mode is set to Manual',
                [
                    'pspReference' =>$notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @return bool
     * @throws \Exception
     */
    private function canCancelPayByLinkOrder(Order $order, Notification $notification): bool
    {
        $payByLinkFailureCount = $order->getPayment()->getAdditionalInformation('payByLinkFailureCount');
        $payByLinkFailureCount = isset($payByLinkFailureCount) ? ++$payByLinkFailureCount : 1;

        $order->getPayment()->setAdditionalInformation('payByLinkFailureCount', $payByLinkFailureCount);

        if ($payByLinkFailureCount >= AdyenPayByLinkConfigProvider::MAX_FAILURE_COUNT) {
            // Order can be cancelled.
            return true;
        }

        $notification->setDone(true);
        $notification->setProcessing(false);

        $this->notificationRepository->save($notification);

        $order->addStatusHistoryComment(__(sprintf(
            "Order wasn't cancelled by this webhook notification. Pay by Link failure count: %s/%s",
            $payByLinkFailureCount,
            AdyenPayByLinkConfigProvider::MAX_FAILURE_COUNT
        )), false);

        $this->adyenLogger->addAdyenNotification(
            __(sprintf(
                "Order wasn't cancelled by this webhook notification. Pay by Link failure count: %s/%s",
                $payByLinkFailureCount,
                AdyenPayByLinkConfigProvider::MAX_FAILURE_COUNT
            )),
            array_merge(
                $this->adyenLogger->getOrderContext($order),
                ['pspReference' => $notification->getPspreference()]
            )
        );

        return false;
    }
}
