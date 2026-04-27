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
                baseComponentConfiguration.data = {};

                if (!quote.isVirtual() && quote.shippingAddress()) {
                    const formattedShippingAddress = this.getFormattedAddress(quote.shippingAddress());

                    baseComponentConfiguration.data.deliveryAddress = {
                        city: formattedShippingAddress.city,
                        country: formattedShippingAddress.country,
                        houseNumberOrName: formattedShippingAddress.houseNumber,
                        postalCode: formattedShippingAddress.postalCode,
                        street: formattedShippingAddress.street
                    }
                }

                if (quote.billingAddress()) {
                    const formattedBillingAddress = this.getFormattedAddress(quote.billingAddress());

                    baseComponentConfiguration.data.billingAddress = {
                        city: formattedBillingAddress.city,
                        country: formattedBillingAddress.country,
                        houseNumberOrName: formattedBillingAddress.houseNumber,
                        postalCode: formattedBillingAddress.postalCode,
                        street: formattedBillingAddress.street
                    };

                    baseComponentConfiguration.data.personalDetails = {
                        firstName: formattedBillingAddress.firstName,
                        lastName: formattedBillingAddress.lastName,
                        telephoneNumber: formattedBillingAddress.telephone,
                        shopperEmail: customerData?.email ?? quote?.guestEmail ?? '',
                        dateOfBirth: customerData?.dob ?? ''
                    }
                }

                return baseComponentConfiguration;
            }
        })
    }
);
