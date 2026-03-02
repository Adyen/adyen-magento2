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

    public function __construct(
        CaseManagement $caseManagement,
        PaymentMethods $paymentMethodsHelper,
        Order $orderHelper
    ) {
        $this->caseManagementHelper = $caseManagement;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->orderHelper = $orderHelper;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $order = $this->caseManagementHelper->markCaseAsAccepted($order, sprintf(
            'Manual review accepted for order w/pspReference: %s',
            $notification->getOriginalReference()
        ));

        // Finalize order only in case of auto capture. For manual capture the capture notification will initiate this finalizeOrder call
        if ($this->paymentMethodsHelper->isAutoCapture($order, $notification->getPaymentMethod())) {
            $order = $this->orderHelper->finalizeOrder(
                $order,
                $notification->getPspreference(),
                $notification->getAmountValue()
            );
        }

        return $order;
    }
}
