/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'Adyen_Payment/js/view/payment/method-renderer/multishipping/adyen-pm-method'
    ],
    function(
        adyenPaymentMethod
    ) {
        return adyenPaymentMethod.extend({
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                var ratePayId = window.checkoutConfig.payment.adyenPm.ratePayId;
                var dfValueRatePay = window.checkoutConfig.payment.adyenPm.deviceIdentToken;

                window.di = {
                    t: dfValueRatePay.replace(':', ''),
                    v: ratePayId,
                    l: 'Checkout',
                };

                // Load Ratepay script
                var ratepayScriptTag = document.createElement('script');
                ratepayScriptTag.src = '//d.ratepay.com/' + ratePayId + '/di.js';
                ratepayScriptTag.type = 'text/javascript';
                document.body.appendChild(ratepayScriptTag);

                return baseComponentConfiguration = this._super();
            }
        })
    }
);
