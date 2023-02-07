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
    protected PaymentMethods $paymentMethodsHelper;

    /**
     * @param PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * @param string $cartId
     * @param AddressInterface|null $shippingAddress
     * @param string|null $shopperLocale
     * @return string
     */
    public function getPaymentMethods(
      string $cartId,
      AddressInterface $billingAddress = null,
      ?string $shopperLocale = null
    ): string {
        // if billingAddress is provided use this country
        $country = null;
        if ($billingAddress) {
            $country = $billingAddress->getCountryId();
        }

        return $this->paymentMethodsHelper->getPaymentMethods($cartId, $country, $shopperLocale);
    }
}
