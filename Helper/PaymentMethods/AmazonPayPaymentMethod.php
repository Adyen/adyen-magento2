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

/** TODO: This PM can be enabled for recurring purposes once tested */
class AmazonPayPaymentMethod extends AbstractWalletPaymentMethod
{
    const TX_VARIANT = 'amazonpay';
    const NAME = 'Amazon Pay';

    public function getTxVariant(): string
    {
        return self::TX_VARIANT;
    }

    public function getPaymentMethodName(): string
    {
        return self::NAME;
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
}
