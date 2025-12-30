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

use Adyen\AdyenException;
use Adyen\Payment\Block\Checkout\Multishipping\Billing;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethodsFilter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

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

    /**
     * @param Billing $billing
     * @param array $result
     * @return array
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
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
