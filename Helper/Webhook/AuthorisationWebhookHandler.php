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
use Adyen\Payment\Model\AuthorizationHandler;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;

class AuthorisationWebhookHandler implements WebhookHandlerInterface
{
    /**
     * @param OrderHelper $orderHelper
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param CartRepositoryInterface $cartRepository
     * @param AdyenNotificationRepositoryInterface $notificationRepository
     * @param CleanupAdditionalInformationInterface $cleanupAdditionalInformation
     * @param AuthorizationHandler $authorizationHandler
     */
    public function __construct(
        private readonly OrderHelper $orderHelper,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AdyenNotificationRepositoryInterface $notificationRepository,
        private readonly CleanupAdditionalInformationInterface $cleanupAdditionalInformation,
        private readonly AuthorizationHandler $authorizationHandler
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
        $paymentMethod =  $notification->getPaymentMethod();
        $pspReference = $notification->getPspReference();
        $merchantReference = $notification->getMerchantReference();
        $amountValue = $notification->getAmountValue();
        $amountCurrency = $notification->getAmountCurrency();

        $order = $this->authorizationHandler->execute($order, $paymentMethod, $merchantReference, $pspReference, $amountValue, $amountCurrency, $notification);

        $payment = $order->getPayment();
        $this->deactivateQuoteIfNeeded($order);
        $this->cleanupAdditionalInformation->execute($payment);
        $this->orderHelper->addWebhookStatusHistoryComment($order, $notification);

        return $order;
    }

    private function deactivateQuoteIfNeeded(Order $order): void
    {
        $quoteId = $order->getQuoteId();
        if (!$quoteId) {
            // No quote associated with the order (or already cleaned up)
            return;
        }

        try {
            $quote = $this->cartRepository->get($quoteId);

            if ($quote->getIsActive()) {
                $quote->setIsActive(false);
                $this->cartRepository->save($quote);
            }
        } catch (\Exception $e) {
            $this->adyenLogger->addAdyenNotification(
                'Quote deactivation skipped during webhook processing.',
                [
                    'quoteId' => $quoteId,
                    'error' => $e->getMessage()
                ]
            );
        }
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
