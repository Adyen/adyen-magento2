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

use Adyen\AdyenException;
use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order as MagentoOrder;

class OfferClosedWebhookHandler implements WebhookHandlerInterface
{
    /**
     * @param PaymentMethods $paymentMethodsHelper
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param Order $orderHelper
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     * @param CleanupAdditionalInformationInterface $cleanupAdditionalInformation
     */
    public function __construct(
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly Order $orderHelper,
        private readonly OrderPaymentResourceModel $orderPaymentResourceModel,
        private readonly CleanupAdditionalInformationInterface $cleanupAdditionalInformation
    ) { }

    /**
     * @throws LocalizedException
     * @throws AdyenException
     */
    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        //Do not process OfferClosed for Pay by Link payments
        if($order->getPayment()->getMethod() == 'adyen_pay_by_link')
            return $order;

        $capturedAdyenOrderPayments = $this->orderPaymentResourceModel->getLinkedAdyenOrderPayments(
            $order->getPayment()->getEntityId()
        );

        if (!empty($capturedAdyenOrderPayments)) {
            $order->addCommentToStatusHistory(
                __('Order is not cancelled because this order was fully/partially authorised.')
            );

            $this->adyenLogger->addAdyenNotification(
                'Order is not cancelled because this order was fully/partially authorised.',
                [
                    'pspReference' => $notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );

            return $order;
        }

        $identicalPaymentMethods = $this->paymentMethodsHelper->compareOrderAndWebhookPaymentMethods(
            $order,
            $notification
        );

        if (!$identicalPaymentMethods) {
            $paymentMethodInstance = $order->getPayment()->getMethodInstance();
            $this->adyenLogger->addAdyenNotification(sprintf(
                'Payment method of notification %s (%s) does not match the payment method (%s) of order %s',
                $notification->getId(),
                $notification->getPaymentMethod(),
                $order->getIncrementId(),
                $this->paymentMethodsHelper->getAlternativePaymentMethodTxVariant(
                    $paymentMethodInstance)
            ),
                [
                    'pspReference' => $notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );

            return $order;
        }

        // Move the order from PAYMENT_REVIEW to NEW, so that it can be cancelled
        if (!$order->isCanceled()
            && !$order->canCancel()
            && $this->configHelper->getNotificationsCanCancel($order->getStoreId())
        ) {
            $order->setState(MagentoOrder::STATE_NEW);
        }

        // Clean-up the data temporarily stored in `additional_information`
        $this->cleanupAdditionalInformation->execute($order->getPayment());

        $this->orderHelper->holdCancelOrder($order, true);

        return $order;
    }
}
