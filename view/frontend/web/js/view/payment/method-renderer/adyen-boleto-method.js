/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
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

                if (quote.billingAddress()) {
                    const formattedBillingAddress = this.getFormattedAddress(quote.billingAddress());

                    baseComponentConfiguration.data = {
                        firstName: formattedBillingAddress.firstName,
                        lastName: formattedBillingAddress.lastName,
                        billingAddress: {
                            city: formattedBillingAddress.city,
                            country: formattedBillingAddress.country,
                            houseNumberOrName: formattedBillingAddress.houseNumber,
                            postalCode: formattedBillingAddress.postalCode,
                            street: formattedBillingAddress.street
                        }
                    }
                }

                return baseComponentConfiguration;
            }
        })
    }
);
