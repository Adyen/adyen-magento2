/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
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
                let formattedBillingAddress = {};
                let shopperDateOfBirth = '';
                let email = {};

                if (!!customerData.dob){
                    shopperDateOfBirth = customerData.dob;
                }

                if (!!customerData.email) {
                    email = customerData.email;
                } else if (!!quote.guestEmail) {
                    email = quote.guestEmail;
                };

                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (!quote.isVirtual() && !!quote.billingAddress()) {
                    formattedBillingAddress = self.getFormattedAddress(quote.billingAddress());
                }

                if (formattedShippingAddress) {
                    baseComponentConfiguration.data.deliveryAddress = {
                        city: formattedShippingAddress.city,
                        country: formattedShippingAddress.country,
                        houseNumberOrName: formattedShippingAddress.houseNumber,
                        postalCode: formattedShippingAddress.postalCode,
                        street: formattedShippingAddress.street
                    }
                }

                if (formattedBillingAddress){
                    baseComponentConfiguration.data.personalDetails = {
                        firstName: formattedBillingAddress.firstName,
                        lastName: formattedBillingAddress.lastName,
                        telephoneNumber: formattedBillingAddress.telephone,
                        shopperEmail: email,
                        dateOfBirth: shopperDateOfBirth,
                    }
                    baseComponentConfiguration.data.billingAddress = {
                        city: formattedBillingAddress.city,
                        country: formattedBillingAddress.country,
                        houseNumberOrName: formattedBillingAddress.houseNumber,
                        postalCode: formattedBillingAddress.postalCode,
                        street: formattedBillingAddress.street,
                    }
                }

                return baseComponentConfiguration;
            }
        })
    }
);
