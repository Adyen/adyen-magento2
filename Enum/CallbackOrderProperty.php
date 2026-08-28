<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Enum;

use Magento\Framework\Phrase;

/**
 * Class CallbackOrderProperty
 *
 * @package Adyen\Payment\Enum
 */
enum CallbackOrderProperty: string
{
    case ShippingFirstName = 'shipping.firstname';
    case ShippingLastName = 'shipping.lastname';
    case ShippingPostCode = 'shipping.postcode';
    case ShippingTelephone = 'shipping.telephone';
    case BillingFirstName = 'billing.firstname';
    case BillingLastName = 'billing.lastname';
    case BillingPostCode = 'billing.postcode';
    case BillingTelephone = 'billing.telephone';
    case CustomerEmail = 'customer_email';

    /**
     * @return Phrase
     */
    public function getLabel(): Phrase
    {
        return match ($this) {
            self::ShippingFirstName => __('Shipping first name'),
            self::ShippingLastName => __('Shipping last name'),
            self::ShippingPostCode => __('Shipping postcode'),
            self::ShippingTelephone => __('Shipping telephone'),
            self::BillingFirstName => __('Billing first name'),
            self::BillingLastName => __('Billing last name'),
            self::BillingPostCode => __('Billing postcode'),
            self::BillingTelephone => __('Billing telephone'),
            self::CustomerEmail  => __('Customer email'),
        };
    }
}
