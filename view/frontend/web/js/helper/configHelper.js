/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define([
    'jquery',
    'Adyen_Payment/js/model/adyen-configuration',
    'Magento_Checkout/js/model/quote'
], function ($, adyenConfiguration, quote) {
    'use strict';

    return {
        /**
         * @param address
         * @returns {{country: (string|*), firstName: (string|*), lastName: (string|*), city: (*|string), street: *, postalCode: (*|string), houseNumber: string, telephone: (string|*)}}
         */
        getFormattedAddress: function (address) {
                let city = '';
                let country = '';
                let postalCode = '';
                let street = '';
                let houseNumber = '';

                city = address.city;
                country = address.countryId;
                postalCode = address.postcode;

                street = address.street.slice(0);

                // address contains line items as an array, otherwise if string just pass along as is
                if (Array.isArray(street)) {
                    // getHouseNumberStreetLine > 0 implies the street line that is used to gather house number
                    if (adyenConfiguration.getHouseNumberStreetLine() > 0) {
                        // removes the street line from array that is used to contain house number
                        houseNumber = street.splice(adyenConfiguration.getHouseNumberStreetLine() - 1, 1);
                    } else if (street.length > 1) { // getHouseNumberStreetLine = 0 uses the last street line as house number
                        // in case there is more than 1 street lines in use, removes last street line from array that should be used to contain house number
                        houseNumber = street.pop();
                    }

                    // Concat street lines except house number
                    street = street.join(' ');
                }

                let firstName = address.firstname;
                let lastName = address.lastname;
                let telephone = address.telephone;

                return {
                    city: city,
                    country: country,
                    postalCode: postalCode,
                    street: street,
                    houseNumber: houseNumber,
                    firstName: firstName,
                    lastName: lastName,
                    telephone: telephone
                };
        },
        getAdyenGender: function (gender) {
            if (gender === 1) {
                return 'MALE';
            } else if (gender === 2) {
                return 'FEMALE';
            }
            return 'UNKNOWN';
        },
      
        buildMultishippingComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo) {
            let self = this;

            let email = '';
            let shopperGender = '';
            let shopperDateOfBirth = '';

            if (customerData.email) {
                email = customerData.email;
            } else if (quote.guestEmail) {
                email = quote.guestEmail;
            }

            shopperGender = customerData.gender;
            shopperDateOfBirth = customerData.dob;

            let formattedShippingAddress = {};
            let formattedBillingAddress = {};

            if (!!quote.shippingAddress()) {
                formattedShippingAddress = this.getFormattedAddress(quote.shippingAddress());
            }

            if (!!quote.billingAddress()) {
                formattedBillingAddress = this.getFormattedAddress(quote.billingAddress());
            }

            /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
            let configuration = Object.assign(paymentMethod,
                {
                    showPayButton: false,
                    countryCode: formattedShippingAddress.country ? formattedShippingAddress.country : formattedBillingAddress.country, // Use shipping address details as default and fall back to billing address if missing
                    hasHolderName: adyenConfiguration.getHasHolderName(),
                    holderNameRequired: adyenConfiguration.getHasHolderName() &&
                        adyenConfiguration.getHolderNameRequired(),
                    data: {
                        personalDetails: {
                            firstName: formattedBillingAddress.firstName,
                            lastName: formattedBillingAddress.lastName,
                            telephoneNumber: formattedBillingAddress.telephone,
                            shopperEmail: email,
                            gender: this.getAdyenGender(shopperGender),
                            dateOfBirth: shopperDateOfBirth,
                        },
                        billingAddress: {
                            city: formattedBillingAddress.city,
                            country: formattedBillingAddress.country,
                            houseNumberOrName: formattedBillingAddress.houseNumber,
                            postalCode: formattedBillingAddress.postalCode,
                            street: formattedBillingAddress.street,
                        },
                    },
                    onChange: function(state) {
                        $('#stateData').val(state.isValid ? JSON.stringify(state.data) : '');
                    },
                    onClick: function(resolve, reject) {
                        if (self.validate()) {
                            resolve();
                        } else {
                            reject();
                        }
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

            // Use extra configuration from the paymentMethodsExtraInfo object if available
            if (paymentMethod.type in paymentMethodsExtraInfo && 'configuration' in paymentMethodsExtraInfo[paymentMethod.type]) {
                configuration = Object.assign(configuration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
            }

            return configuration;
        }
    };
});

