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

use Adyen\Payment\Helper\Order;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order as MagentoOrder;

class CancellationWebhookHandler implements WebhookHandlerInterface
{
    private Order $orderHelper;
    private AdyenLogger $adyenLogger;

    public function __construct(
        Order $orderHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->orderHelper = $orderHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @throws LocalizedException
     */
    public function handleWebhook(
        MagentoOrder $order,
        Notification $notification,
        string $transitionState
    ): MagentoOrder {
        /** @var MagentoOrder\Invoice $invoice */
        $invoices = $order->getInvoiceCollection();

        $allInvoicesCanCancel = true;
        foreach ($invoices as $invoice)  {
            if (!$invoice->canCancel()) {
                $allInvoicesCanCancel = false;
                break;
            }
        }

        if ($allInvoicesCanCancel) {
            foreach ($invoices as $invoice) {
                $invoice->cancel();
                $invoice->save();
            }

            $order = $this->orderHelper->holdCancelOrder($order, true);
        } else {
            $this->adyenLogger->addAdyenNotification(
                "Order can not be cancelled because invoice has been paid.",
                [
                    'pspReference' => $notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );
        }

        return $order;
    }
}
