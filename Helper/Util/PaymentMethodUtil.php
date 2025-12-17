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

namespace Adyen\Payment\Helper\Util;

class PaymentMethodUtil
{
    const MANUAL_CAPTURE_SUPPORTED_PAYMENT_METHODS = [
        'cup',
        'cartebancaire',
        'visa',
        'visadankort',
        'mc',
        'uatp',
        'amex',
        'maestro',
        'maestrouk',
        'diners',
        'discover',
        'jcb',
        'laser',
        'paypal',
        'sepadirectdebit',
        'ach',
        'dankort',
        'elo',
        'hipercard',
        'mc_applepay',
        'visa_applepay',
        'amex_applepay',
        'discover_applepay',
        'maestro_applepay',
        'cartebancaire_applepay',
        'paywithgoogle',
        'mc_googlepay',
        'visa_googlepay',
        'amex_googlepay',
        'discover_googlepay',
        'maestro_googlepay',
        'svs',
        'givex',
        'valuelink',
        'twint',
        'carnet',
        'pix',
        'oney',
        'affirm',
        'bright',
        'amazonpay',
        'applepay',
        'googlepay',
        'mobilepay',
        'vipps',
        'mc_clicktopay',
        'visa_clicktopay',
        'visa_amazonpay',
        'mc_amazonpay',
        'amex_amazonpay',
        'discover_amazonpay',
        'maestro_amazonpay',
        'elo_amazonpay',
        'jcb_amazonpay',
        'bcmc',
        'bcmc_mobile',
        'afterpay',
        'afterpay_b2b',
        'afterpay_default',
        'afterpay_directdebit',
        'afterpaytouch',
        'afterpaytouch_AU',
        'afterpaytouch_CA',
        'afterpaytouch_NZ',
        'afterpaytouch_US',
        'clearpay',
        'facilypay',
        'facilypay_10x',
        'facilypay_10x_merchant_pays',
        'facilypay_10x_withfees',
        'facilypay_12x',
        'facilypay_12x_merchant_pays',
        'facilypay_12x_withfees',
        'facilypay_3x',
        'facilypay_3x_merchant_pays',
        'facilypay_3x_withfees',
        'facilypay_4x',
        'facilypay_4x_merchant_pays',
        'facilypay_4x_withfees',
        'facilypay_6x',
        'facilypay_6x_merchant_pays',
        'facilypay_6x_withfees',
        'facilypay_fr',
        'klarna',
        'klarna_b2b',
        'klarna_paynow',
        'klarna_account',
        'ratepay',
        'ratepay_directdebit',
        'walley',
        'walley_b2b',
        'girocard',
        'girocard_applepay'
    ];

    /**
     * @param string $paymentMethod
     * @return bool
     */
    public static function isManualCaptureSupported(string $paymentMethod): bool
    {
        // Check for payment methods with no variants
        if (in_array($paymentMethod, self::MANUAL_CAPTURE_SUPPORTED_PAYMENT_METHODS)) {
            return true;
        }

        //Regex pattern for payment methods with variants
        $paymentMethodsWithVariants = '/^afterpay|^boleto|^clearpay|^ratepay|^zip/';

        //Check the payment methods with variants
        if (preg_match($paymentMethodsWithVariants, $paymentMethod)) {
            return true;
        }

        return false;
    }
}
