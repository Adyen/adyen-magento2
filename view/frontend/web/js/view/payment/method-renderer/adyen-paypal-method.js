/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
    ],
    function(
        quote,
        adyenPaymentMethod,
    ) {
        return adyenPaymentMethod.extend({
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo, result) {
                let baseComponentConfiguration = this._super();
                let paypalConfiguration = Object.assign(baseComponentConfiguration.showPayButton=true, paymentMethodsExtraInfo[paymentMethod.methodIdentifier].configuration,);
                return paypalConfiguration
            }
        })
    }
);
