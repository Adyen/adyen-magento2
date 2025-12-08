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
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethodsFilter;

class MultishippingPaymentMethods
{
    protected PaymentMethodsFilter $paymentMethodsFilter;
    protected Config $configHelper;

    public function __construct(
        PaymentMethodsFilter $paymentMethodsFilter,
        Config $configHelper
    ) {
        $this->paymentMethodsFilter = $paymentMethodsFilter;
        $this->configHelper = $configHelper;
    }

    public function afterGetMethods(
        Billing $billing,
        array $result
    ): array {
        $quote = $billing->getQuote();
        $storeId = $quote->getStoreId();

        if (!$this->configHelper->getIsPaymentMethodsActive($storeId)) {
            return $result;
        }

        list ($result, $adyenMethods) = $this->paymentMethodsFilter->sortAndFilterPaymentMethods($result, $quote);
        $billing->setAdyenPaymentMethodsResponse($adyenMethods);

        return $result;
    }
}
