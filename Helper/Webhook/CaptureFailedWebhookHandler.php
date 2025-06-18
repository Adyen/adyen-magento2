<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;
use Adyen\Payment\Logger\AdyenLogger;

class CaptureFailedWebhookHandler implements WebhookHandlerInterface
{
    /** @var AdyenLogger */
    private AdyenLogger $adyenLogger;

    /**
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(AdyenLogger $adyenLogger)
    {
        $this->adyenLogger = $adyenLogger;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $message = __(
            "Capture attempt for payment with reference %1 failed. Please visit Customer Area for further details.",
            $notification->getOriginalReference()
        );

        // Failure `reason` has not been added to the log intentionally as it might contain some sensitive data.
        $this->adyenLogger->addAdyenNotification($message,  [
            'capturePspReference' => $notification->getPspreference(),
            'paymentPspReference' => $notification->getOriginalReference(),
            'merchantReference' => $notification->getMerchantReference()
        ]);

        // The reason can be seen on the order comment history.
        $message .= '<br />' . __("Reason: %1", $notification->getReason());


        $order->addCommentToStatusHistory($message);

        return $order;
    }
}
