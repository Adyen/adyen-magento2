<?php
/**
 *
 * Adyen Payment Module
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2022 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\Payment\Helper\PaymentMethods;


class PaymentMethodFactory
{
    public static function createAdyenPaymentMethod(string $txVariant): PaymentMethodInterface
    {
        switch ($txVariant) {
            case 'paypal':
                return new PayPalPaymentMethod();
        }
    }
}