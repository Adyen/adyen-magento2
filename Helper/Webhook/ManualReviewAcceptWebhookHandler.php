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
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;

class ManualReviewAcceptWebhookHandler implements WebhookHandlerInterface
{
    /** @var CaseManagement */
    private $caseManagementHelper;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    /** @var Order */
    private $orderHelper;

    /** @var AdyenOrderPayment */
    private $adyenOrderPaymentHelper;

    public function __construct(
        CaseManagement $caseManagement,
        PaymentMethods $paymentMethodsHelper,
        Order $orderHelper,
        AdyenOrderPayment $adyenOrderPaymentHelper
    ) {
        $this->caseManagementHelper = $caseManagement;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->orderHelper = $orderHelper;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $isAutoCapture = $this->adyenOrderPaymentHelper->isAutoCaptured(
            $order,
            (string) $notification->getOriginalReference()
        ) ?? $this->paymentMethodsHelper->isAutoCapture($order, $notification->getPaymentMethod());

        $comment = sprintf(
            'Manual review accepted for order w/pspReference: %s',
            $notification->getOriginalReference()
        );
        if (!$isAutoCapture) {
            $comment .= '. The order will be finalized once the capture webhook is received.';
        }

        $order = $this->caseManagementHelper->markCaseAsAccepted($order, $comment);

        // Finalize order only in case of auto capture. For manual capture the capture notification will initiate this finalizeOrder call
        if ($isAutoCapture) {
            $order = $this->orderHelper->finalizeOrder($order, $notification);
        }

        return $order;
    }
}
