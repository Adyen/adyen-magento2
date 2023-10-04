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

namespace Adyen\Payment\Block\Checkout\Multishipping;

class Billing extends \Magento\Multishipping\Block\Checkout\Billing
{
    public $adyenPaymentMethodsResponse;

    public function getAdyenPaymentMethodsResponse(): string
    {
        return $this->adyenPaymentMethodsResponse;
    }

    public function setAdyenPaymentMethodsResponse(string $adyenPaymentMethodsResponse): void
    {
        $this->adyenPaymentMethodsResponse = $adyenPaymentMethodsResponse;
    }
}
