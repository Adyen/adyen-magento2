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
                var self = this;
                var email = '';
                var showPayButton = false;

                if (!!quote.guestEmail) {
                    email = quote.guestEmail;
                }
                var formattedShippingAddress = {};
                var formattedBillingAddress = {};

                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (!!quote.billingAddress()) {
                    formattedBillingAddress = self.getFormattedAddress(quote.billingAddress());
                }
                /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
                var configuration = Object.assign(paymentMethod,
                    {
                        showPayButton: showPayButton,
                        countryCode: formattedShippingAddress.country ? formattedShippingAddress.country : formattedBillingAddress.country, // Use shipping address details as default and fall back to billing address if missing
                        data: {},
                        onChange: function (state) {
                            result.isPlaceOrderAllowed(state.isValid);
                        },
                    });

                if (formattedShippingAddress) {
                    configuration.data.shippingAddress = {
                        city: formattedShippingAddress.city,
                        country: formattedShippingAddress.country,
                        houseNumberOrName: formattedShippingAddress.houseNumber,
                        postalCode: formattedShippingAddress.postalCode,
                        street: formattedShippingAddress.street
                    };
                }
                return configuration
            }
        })
    }
);
