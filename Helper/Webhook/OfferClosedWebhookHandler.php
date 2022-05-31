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
        $ignoreHasInvoice = true;

        // if payment is API, check if API result pspreference is the same as reference
        if ($notification->getEventCode() == Notification::AUTHORISATION) {
            if ('api' === $order->getPayment()->getPaymentMethodType()) {
                // don't cancel the order because order was successful through api
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'order is not cancelled because api result was successful'
                );

                return $order;
            }
            $ignoreHasInvoice = false;
        }

        /*
         * Don't cancel the order if part of the payment has been captured.
         * Partial payments can fail, if the second payment has failed then the first payment is
         * refund/cancelled as well. So if it is a partial payment that failed cancel the order as well
         * TODO: Refactor this
         */
        $paymentPreviouslyCaptured = $order->getData('adyen_notification_payment_captured');

        if ($previousAdyenEventCode == "AUTHORISATION : TRUE" || !empty($paymentPreviouslyCaptured)) {
            $this->adyenLogger->addAdyenNotificationCronjob(
                'Order is not cancelled because previous notification
                                    was an authorisation that succeeded and payment was captured'
            );

            return $order;
        }

        $notificationPaymentMethod = $notification->getPaymentMethod();

        /*
        * For cards, it can be 'VI', 'MI',...
        * For alternatives, it can be 'ideal', 'directEbanking',...
        */
        $orderPaymentMethod = $order->getPayment()->getCcType();

        /*
         * Returns if the payment method is wallet like wechatpayWeb, amazonpay, applepay, paywithgoogle
         */
        $isWalletPaymentMethod = $this->paymentMethodsHelper->isWalletPaymentMethod($orderPaymentMethod);
        $isCCPaymentMethod = $order->getPayment()->getMethod() === 'adyen_cc' || $order->getPayment()->getMethod() === 'adyen_oneclick';

        /*
        * If the order was made with an Alternative payment method,
        *  continue with the cancellation only if the payment method of
        * the notification matches the payment method of the order.
        */
        if (!$isWalletPaymentMethod && !$isCCPaymentMethod && strcmp($notificationPaymentMethod, $orderPaymentMethod) !== 0) {
            $this->adyenLogger->addAdyenNotificationCronjob(
                "The notification does not match the payment method of the order,
                    skipping OFFER_CLOSED"
            );
            return $order;
        }

        // Move the order from PAYMENT_REVIEW to NEW, so that can be cancelled
        if (!$order->canCancel() && $this->configHelper->getNotificationsCanCancel($order->getStoreId())) {
            $order->setState(MagentoOrder::STATE_NEW);
        }

        $this->orderHelper->holdCancelOrder($order, $ignoreHasInvoice);

        return $order;
    }
}