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


use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;

class AuthorisationWebhookHandler implements WebhookHandlerInterface
{
    /** @var WebhookService */
    private $webhookService;

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

    public function __construct(
        WebhookService $webhookService,
        AdyenOrderPayment $adyenOrderPayment,
        OrderHelper $orderHelper,
        CaseManagement $caseManagementHelper,
        SerializerInterface $serializer,
        AdyenLogger $adyenLogger
    )
    {
        $this->webhookService = $webhookService;
        $this->adyenOrderPaymentHelper = $adyenOrderPayment;
        $this->orderHelper = $orderHelper;
        $this->caseManagementHelper = $caseManagementHelper;
        $this->serializer = $serializer;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param string $transitionState
     * @throws \Exception
     */
    public function handleWebhook(Order $order, Notification $notification, string $transitionState)
    {
        $isAutoCapture = $this->webhookService->isAutoCapture($order, $notification->getPaymentMethod());

        // Set adyen_notification_payment_captured to true so that we ignore a possible OFFER_CLOSED
        if ($notification->isSuccessful() && $isAutoCapture) {
            $order->setData('adyen_notification_payment_captured', 1);
        }

        $this->adyenOrderPaymentHelper->createAdyenOrderPayment($order, $notification, $isAutoCapture);
        $isFullAmountAuthorized = $this->adyenOrderPaymentHelper->isFullAmountAuthorized($order);

        if ($isFullAmountAuthorized) {
            $this->webhookService->setPrePaymentAuthorized($order);
            $this->orderHelper->updatePaymentDetails($order, $notification);
            $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize($notification->getAdditionalData()) : "";
            $requireFraudManualReview = $this->caseManagementHelper->requiresManualReview($additionalData);

            if ($isAutoCapture && !$requireFraudManualReview) {
                $this->webhookService->createInvoice($order, $notification, true);
                $this->webhookService->finalizeOrder($order, $notification);
            } elseif ($isAutoCapture && $requireFraudManualReview) {
                $this->webhookService->createInvoice($order, $notification, true);
                $this->caseManagementHelper->markCaseAsPendingReview($order, $notification->getPspreference(), true);
            } elseif (!$isAutoCapture && !$requireFraudManualReview) {
                $order = $this->orderHelper->addWebhookStatusHistoryComment($order, $notification);
                $order->addStatusHistoryComment(__('Capture Mode set to Manual'), $order->getStatus());
                $this->adyenLogger->addAdyenNotificationCronjob('Capture mode is set to Manual');
            } elseif (!$isAutoCapture && $requireFraudManualReview) {
                $this->caseManagementHelper->markCaseAsPendingReview($order, $notification->getPspreference(), false);

            }

            // For Boleto confirmation mail is sent on order creation
            // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
            if ($notification->getPaymentMethod() != "adyen_boleto" && !$order->getEmailSent()) {
                $this->orderHelper->sendOrderMail($order);
            }
        } else {
            $this->orderHelper->addWebhookStatusHistoryComment($order, $notification);
        }

        /* @TODO Continue huereeee*/
        // Set authorized amount in sales_order_payment
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($this->order, false);
        $orderAmount = $orderAmountCurrency->getAmount();
        $this->order->getPayment()->setAmountAuthorized($orderAmount);

        if ($notification->getPaymentMethod() == "c_cash" &&
            $this->configHelper->getConfigData('create_shipment', 'adyen_cash', $this->order->getStoreId())
        ) {
            $this->createShipment();
        }
    }
}
