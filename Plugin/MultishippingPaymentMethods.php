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

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Block\Checkout\Multishipping\Billing;
use Adyen\Payment\Helper\PaymentMethodsFilter;

class MultishippingPaymentMethods
{
    protected PaymentMethodsFilter $paymentMethodsFilter;

    public function __construct(
        PaymentMethodsFilter $paymentMethodsFilter
    ) {
        $this->paymentMethodsFilter = $paymentMethodsFilter;
    }

    public function afterGetMethods(
        Billing $billing,
        array $result
    ): array {
        $quote = $billing->getQuote();
        list ($result, $adyenMethods) = $this->paymentMethodsFilter->sortAndFilterPaymentMethods($result, $quote);
        $billing->setAdyenPaymentMethodsResponse($adyenMethods);

        return $result;
    }
}
