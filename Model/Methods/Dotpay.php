<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Model\Methods;

use Adyen\Payment\Helper\PaymentMethods\PaymentMethodInterface;
use Adyen\Payment\Model\AdyenPaymentMethod;

class Dotpay extends AdyenPaymentMethod implements PaymentMethodInterface
{
    const CODE = 'adyen_dotpay';
    const TX_VARIANT = 'dotpay';
    const NAME = 'DotPay';

    public function supportsRecurring(): bool
    {
        return false;
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
        return false;
    }

    public function supportsSubscription(): bool
    {
        return false;
    }

    public function supportsUnscheduledCardOnFile(): bool
    {
        return false;
    }

    public function isWallet(): bool
    {
        return false;
    }
}
