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
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;

class ManualReviewRejectWebhookHandler implements WebhookHandlerInterface
{
    /** @var CaseManagement */
    private $caseManagementHelper;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    public function __construct(
        CaseManagement $caseManagement,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->caseManagementHelper = $caseManagement;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $isAutoCapture = $this->paymentMethodsHelper->isAutoCapture($order, $notification->getPaymentMethod());

        return $this->caseManagementHelper->markCaseAsRejected($order, $notification->getOriginalReference(), $isAutoCapture);
    }
}
