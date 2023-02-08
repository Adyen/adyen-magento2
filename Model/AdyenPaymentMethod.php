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
namespace Adyen\Payment\Model;

use Adyen\Payment\Model\Method\Adapter;

class AdyenPaymentMethod extends Adapter
{
    const CODE = 'adyen';
    const TX_VARIANT = 'adyen';
    const NAME = 'adyen';

    public function getCode(): string
    {
        return static::CODE;
    }

    public function getTxVariant(): string
    {
        return static::TX_VARIANT;
    }

    public function getPaymentMethodName(): string
    {
        return static::NAME;
    }
}
