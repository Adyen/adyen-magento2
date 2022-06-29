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


use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Logger\AdyenLogger;

class PaymentMethodFactory
{
    private static $adyenLogger;

    public function __construct(AdyenLogger $adyenLogger)
    {
        self::$adyenLogger = $adyenLogger;
    }

    /**
     * @throws PaymentMethodException
     */
    public static function createAdyenPaymentMethod(string $txVariant): PaymentMethodInterface
    {
        switch ($txVariant) {
            case GooglePayPaymentMethod::TX_VARIANT:
                return new GooglePayPaymentMethod();
            case PayPalPaymentMethod::TX_VARIANT:
                return new PayPalPaymentMethod();
            case SepaPaymentMethod::TX_VARIANT:
                return new SepaPaymentMethod();
            default:
                $message = __('%s: %s', __('Unknown txVariant', $txVariant));
                self::$adyenLogger->error($message);
                throw new PaymentMethodException($message);
        }
    }
}
