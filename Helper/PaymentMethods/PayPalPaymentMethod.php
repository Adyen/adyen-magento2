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
namespace Adyen\Payment\Helper\PaymentMethods;

class PayPalPaymentMethod implements PaymentMethodInterface
{
    public function getTxVariant(): string
    {
        return 'paypal';
    }

    public function getPaymentMethodName(): string
    {
        return 'PayPal';
    }

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function supportsManualCapture(): bool
    {
        return true;
    }

    public function supportsAutoCapture(): bool
    {
        return true;
    }

    public function getLabel(): string
    {
        return 'xyz';
    }
}
