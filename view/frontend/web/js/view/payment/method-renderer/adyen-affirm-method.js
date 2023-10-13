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
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
    ],
    function(
        adyenPaymentMethod
    ) {
        return adyenPaymentMethod.extend({
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();

                // Custom component configuration
                baseComponentConfiguration.visibility = {
                    personalDetails: "hidden",
                    billingAddress: "hidden",
                    deliveryAddress: "hidden"
                }
                // End of custom component configuration

                return baseComponentConfiguration;
            }
        })
    }
);
