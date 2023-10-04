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
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();
                let self = this;
                let formattedShippingAddress = {};

                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (formattedShippingAddress) {
                    baseComponentConfiguration.data.shippingAddress = {
                        city: formattedShippingAddress.city,
                        country: formattedShippingAddress.country,
                        houseNumberOrName: formattedShippingAddress.houseNumber,
                        postalCode: formattedShippingAddress.postalCode,
                        street: formattedShippingAddress.street
                    }
                }
                return baseComponentConfiguration;
            }
        })
    }
);
