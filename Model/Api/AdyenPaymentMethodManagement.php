<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Adyen\Payment\Helper\PaymentMethods;

class AdyenPaymentMethodManagement implements AdyenPaymentMethodManagementInterface
{
    protected PaymentMethods $paymentMethodsHelper;

    public function __construct(
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    public function getPaymentMethods(
        string $cartId,
        ?string $shopperLocale = null,
        ?string $country = null,
        ?string $channel = null
    ) : string
    {
        return $this->paymentMethodsHelper->getPaymentMethods($cartId, $country, $shopperLocale, $channel);
    }
}
