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


use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Webhook\PaymentStates;
use Adyen\Payment\Exception\AdyenWebhookException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;

class AuthorisationWebhookHandler implements WebhookHandlerInterface
{
    /** @var AdyenOrderPayment */
    private $adyenOrderPaymentHelper;

    /** @var OrderHelper */
    private $orderHelper;

    /** @var CaseManagement */
    private $caseManagementHelper;

    /** @var SerializerInterface */
    private $serializer;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var ChargedCurrency */
    private $chargedCurrency;

    /** @var Config */
    private $configHelper;

    /** @var Invoice */
    private $invoiceHelper;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    public function __construct(
        AdyenOrderPayment $adyenOrderPayment,
        OrderHelper $orderHelper,
        CaseManagement $caseManagementHelper,
        SerializerInterface $serializer,
        AdyenLogger $adyenLogger,
        ChargedCurrency $chargedCurrency,
        Config $configHelper,
        Invoice $invoiceHelper,
        PaymentMethods $paymentMethodsHelper
    )
    {
        $this->adyenOrderPaymentHelper = $adyenOrderPayment;
        $this->orderHelper = $orderHelper;
        $this->caseManagementHelper = $caseManagementHelper;
        $this->serializer = $serializer;
        $this->adyenLogger = $adyenLogger;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->invoiceHelper = $invoiceHelper;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param string $transitionState
     * @return Order
     * @throws LocalizedException
     */
    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order
    {
        $isAutoCapture = $this->paymentMethodsHelper->isAutoCapture($order, $notification->getPaymentMethod());
        if ($transitionState === PaymentStates::STATE_PAID && $isAutoCapture) {
            $order = $this->handleSuccessfulAutoCaptureAuthorisation($order, $notification);
        } elseif ($transitionState === PaymentStates::STATE_PAID && !$isAutoCapture) {
            $order = $this->handleSuccessfulManualCaptureAuthorisation($order, $notification);
        } elseif ($transitionState === PaymentStates::STATE_FAILED) {
            $order = $this->handleFailedAuthorisation($order, $notification);
        }

        return $order;
    }

    private function handleSuccessfulManualCaptureAuthorisation(Order $order, Notification $notification): Order
    {
        $this->adyenOrderPaymentHelper->createAdyenOrderPayment($order, $notification, OrderPaymentInterface::CAPTURE_STATUS_NO_CAPTURE);
        $isFullAmountAuthorized = $this->adyenOrderPaymentHelper->isFullAmountAuthorized($order);

        if ($isFullAmountAuthorized) {
            $order = $this->orderHelper->setPrePaymentAuthorized($order);
            $this->orderHelper->updatePaymentDetails($order, $notification);
            $requireFraudManualReview = $this->caseManagementHelper->requiresManualReview($notification);
            $order = $this->handleManualCapture($order, $notification, $requireFraudManualReview);

            // For Boleto confirmation mail is sent on order creation
            // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
            if ($notification->getPaymentMethod() != "adyen_boleto" && !$order->getEmailSent()) {
                $this->orderHelper->sendOrderMail($order);
            }
        }  else {
            $this->orderHelper->addWebhookStatusHistoryComment($order, $notification);
        }

        // Set authorized amount in sales_order_payment
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $orderAmount = $orderAmountCurrency->getAmount();
        $order->getPayment()->setAmountAuthorized($orderAmount);

        if ($notification->getPaymentMethod() == "c_cash" &&
            $this->configHelper->getConfigData('create_shipment', 'adyen_cash', $order->getStoreId())
        ) {
            $this->orderHelper->createShipment($order);
        }

        return $order;
    }

    private function handleSuccessfulAutoCaptureAuthorisation(Order $order, Notification $notification): Order
    {
        // Set adyen_notification_payment_captured to true so that we ignore a possible OFFER_CLOSED
        if ($notification->isSuccessful()) {
            $order->setData('adyen_notification_payment_captured', 1);
        }

        $this->adyenOrderPaymentHelper->createAdyenOrderPayment($order, $notification, OrderPaymentInterface::CAPTURE_STATUS_AUTO_CAPTURE);
        $isFullAmountAuthorized = $this->adyenOrderPaymentHelper->isFullAmountAuthorized($order);

        if ($isFullAmountAuthorized) {
            $order = $this->orderHelper->setPrePaymentAuthorized($order);
            $this->orderHelper->updatePaymentDetails($order, $notification);
            $requireFraudManualReview = $this->caseManagementHelper->requiresManualReview($notification);
            $order = $this->handleAutoCapture($order, $notification, $requireFraudManualReview);

            // For Boleto confirmation mail is sent on order creation
            // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
            if ($notification->getPaymentMethod() != "adyen_boleto" && !$order->getEmailSent()) {
                $this->orderHelper->sendOrderMail($order);
            }
        } else {
            $this->adyenLogger->addAdyenNotification(sprintf(
                    'Full amount not authorized for psp reference %s and order %s.',
                    $notification->getPspReference(),
                    $order->getIncrementId()
                ));
            throw new AdyenWebhookException(__(sprintf(
                'Full amount not authorized for psp reference %s and order %s',
                $notification->getPspreference(),
                $order->getIncrementId()
            )));
        }

        // Set authorized amount in sales_order_payment
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $orderAmount = $orderAmountCurrency->getAmount();
        $order->getPayment()->setAmountAuthorized($orderAmount);

        if ($notification->getPaymentMethod() == "c_cash" &&
            $this->configHelper->getConfigData('create_shipment', 'adyen_cash', $order->getStoreId())
        ) {
            $this->orderHelper->createShipment($order);
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
        $this->invoiceHelper->createPaidInvoice($order, $notification);
        if ($requireFraudManualReview) {
            $order->setIsInProcess(false);
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $notification->getPspreference());
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
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $notification->getPspreference());
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
        $notification->save();

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
