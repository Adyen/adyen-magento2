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
namespace Adyen\Payment\Model\Methods;

use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Adyen\Payment\Model\AdyenPaymentMethod;

class Paypal extends AdyenPaymentMethod implements PaymentMethodInterface
{
    const CODE = 'adyen_paypal';
    const TX_VARIANT = 'paypal';
    const NAME = 'PayPal';

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

    public function supportsCardOnFile(): bool
    {
        return true;
    }

    public function supportsSubscription(): bool
    {
        return true;
    }

    public function supportsUnscheduledCardOnFile(): bool
    {
        return true;
    }

    public function isWallet(): bool
    {
        return false;
    }
}
