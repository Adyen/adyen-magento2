<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\InstantPurchase\PaymentMethodIntegration;

use Magento\InstantPurchase\PaymentMethodIntegration\AvailabilityCheckerInterface;

interface AdyenAvailabilityCheckerInterface extends AvailabilityCheckerInterface
{
    /**
     * Checks if Adyen alternative payment method may be used for instant purchase.
     *
     * This interface extends the default `AvailabilityCheckerInterface` and implements
     * a new method with payment method argument. This interface is used in `InstantPurchaseIntegrations`
     * plugin to override the `AvailabilityCheckerInterface` which doesn't have payment method argument.
     *
     * @param string $paymentMethodCode
     *
     * @return bool
     */
    public function isAvailableAdyenMethod(string $paymentMethodCode): bool;
}
