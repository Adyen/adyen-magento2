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
        $txVariantObject = new TxVariant($txVariant);

        switch ($txVariantObject->getPaymentMethod()) {
            case PayPalPaymentMethod::TX_VARIANT:
                return new PayPalPaymentMethod();
            case SepaPaymentMethod::TX_VARIANT:
                return new SepaPaymentMethod();
            case ApplePayPaymentMethod::TX_VARIANT:
                return new ApplePayPaymentMethod($txVariantObject->getCard());
            case AmazonPayPaymentMethod::TX_VARIANT:
                return new AmazonPayPaymentMethod($txVariantObject->getCard());
            case GooglePayPaymentMethod::TX_VARIANT:
                return new GooglePayPaymentMethod($txVariantObject->getCard());
            case TwintPaymentMethod::TX_VARIANT:
                return new TwintPaymentMethod($txVariantObject->getCard());
            default:
                $message = sprintf('Unknown txVariant: %s', $txVariant);
                self::$adyenLogger->debug($message);
                throw new PaymentMethodException(__($message));
        }
    }
}
