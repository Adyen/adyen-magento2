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
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;

class PendingWebhookHandler implements WebhookHandlerInterface
{
    /** @var Config */
    private $configHelper;

    /** @var Order */
    private $orderHelper;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    public function __construct(
        Config $configHelper,
        Order $orderHelper,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $sendEmailSepaOnPending = $this->configHelper->getConfigData(
            'send_email_bank_sepa_on_pending',
            'adyen_abstract',
            $order->getStoreId()
        );

        // Check if payment is banktransfer or sepa if true then send out order confirmation email
        if ($sendEmailSepaOnPending && !$order->getEmailSent() &&
            ($this->paymentMethodsHelper->isBankTransfer($notification->getPaymentMethod()) || $notification->getPaymentMethod() == 'sepadirectdebit')) {
            $this->orderHelper->sendOrderMail($order);
        }

        return $order;
    }
}
