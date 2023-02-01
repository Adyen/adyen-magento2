<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Magento\Quote\Api\Data\AddressInterface;

class AdyenPaymentMethodManagement implements AdyenPaymentMethodManagementInterface
{
    protected PaymentMethods $_paymentMethodsHelper;

    public function __construct(
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->_paymentMethodsHelper = $paymentMethodsHelper;
    }

    public function getPaymentMethods(
        string $cartId,
        AddressInterface $shippingAddress = null,
        ?string $shopperLocale = null
    ): string {
        // if shippingAddress is provided use this country
        $country = null;
        if ($shippingAddress) {
            $country = $shippingAddress->getCountryId();
        }

        return $this->_paymentMethodsHelper->getPaymentMethods($cartId, $country, $shopperLocale);
    }
}
