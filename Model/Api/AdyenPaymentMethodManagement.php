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
     * @param string|null $shopperLocale
     * @return string
     */
    public function getPaymentMethods(string $cartId, ?string $shopperLocale = null) : string {

        return $this->paymentMethodsHelper->getPaymentMethods($cartId, $shopperLocale);
    }
}
