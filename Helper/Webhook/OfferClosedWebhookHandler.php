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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order as MagentoOrder;


class OfferClosedWebhookHandler implements WebhookHandlerInterface
{
    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var Config */
    private $configHelper;

    /** @var Order */
    private $orderHelper;

    public function __construct(
        PaymentMethods $paymentMethodsHelper,
        AdyenLogger $adyenLogger,
        Config $configHelper,
        Order $orderHelper
    )
    {
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @throws LocalizedException
     */
    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        //TODO: Refactor code that is common between here and AUTH w/success = false
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

        $identicalPaymentMethods = $this->paymentMethodsHelper->compareOrderAndWebhookPaymentMethods($order, $notification);

        if (!$identicalPaymentMethods) {
            $this->adyenLogger->addAdyenNotification(sprintf(
                'Payment method of notification %s (%s) does not match the payment method (%s) of order %s',
                $notification->getId(),
                $notification->getPaymentMethod(),
                $order->getIncrementId(),
                $order->getPayment()->getCcType()
            ),
                [
                    'pspReference' => $notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );

            return $order;
        }

        // Move the order from PAYMENT_REVIEW to NEW, so that it can be cancelled
        if (!$order->canCancel() && $this->configHelper->getNotificationsCanCancel($order->getStoreId())) {
            $order->setState(MagentoOrder::STATE_NEW);
        }

        $this->orderHelper->holdCancelOrder($order, true);

        return $order;
    }
}
