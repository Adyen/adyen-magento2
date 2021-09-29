/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*global define*/
define([
    'jquery',
    'Adyen_Payment/js/view/payment/method-renderer/adyen-hpp-method',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Adyen_Payment/js/model/adyen-configuration',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader'
], function (
    $,
    Component,
    adyenPaymentService,
    adyenConfiguration,
    quote,
    fullScreenLoader
) {
    'use strict';
    
    return Component.extend({
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/hpp-form'
        },
        initialize: function () {
            var self = this;
            // Retrieve adyen payment methods
            adyenPaymentService.retrievePaymentMethods().done(function(paymentMethods) {
                try {
                    paymentMethods = JSON.parse(paymentMethods);
                } catch(error) {
                    if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                        console.log(error);
                    }
                    paymentMethods = null;
                }

                /** Disable wallet payment methods for multi-shipping */
                paymentMethods.paymentMethodsResponse.paymentMethods = paymentMethods.paymentMethodsResponse.paymentMethods.filter(function (paymentMethod) {
                    return !self.showPayButtonPaymentMethods.includes(paymentMethod.type);
                })

                adyenPaymentService.setPaymentMethods(paymentMethods);
                fullScreenLoader.stopLoader();
            }.bind(self)).fail(function() {
                console.log('Fetching the payment methods failed!');
            });
            this._super();
        },
        buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo, result) {
            var self = this;

            var firstName = '';
            var lastName = '';
            var telephone = '';
            var email = '';
            var shopperGender = '';
            var shopperDateOfBirth = '';

            if (!!customerData.email) {
                email = customerData.email;
            } else if (!!quote.guestEmail) {
                email = quote.guestEmail;
            }

            shopperGender = customerData.gender;
            shopperDateOfBirth = customerData.dob;

            var formattedShippingAddress = {};
            var formattedBillingAddress = {};

            if (!!quote.shippingAddress()) {
                formattedShippingAddress = getFormattedAddress(quote.shippingAddress());
            }

            if (!!quote.billingAddress()) {
                formattedBillingAddress = getFormattedAddress(quote.billingAddress());
            }

            /**
             * @param address
             * @returns {{country: (string|*), firstName: (string|*), lastName: (string|*), city: (*|string), street: *, postalCode: (*|string), houseNumber: string, telephone: (string|*)}}
             */
            function getFormattedAddress(address) {
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
                    } else { // getHouseNumberStreetLine = 0 uses the last street line as house number
                        // in case there is more than 1 street lines in use, removes last street line from array that should be used to contain house number
                        if (street.length > 1) {
                            houseNumber = street.pop();
                        }
                    }

                    // Concat street lines except house number
                    street = street.join(' ');
                }

                firstName = address.firstname;
                lastName = address.lastname;
                telephone = address.telephone;

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
            }

            function getAdyenGender(gender) {
                if (gender == 1) {
                    return 'MALE';
                } else if (gender == 2) {
                    return 'FEMALE';
                }
                return 'UNKNOWN';

            }

            /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
            var configuration = Object.assign(paymentMethod,
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
                            gender: getAdyenGender(shopperGender),
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
                        result.isPlaceOrderAllowed(state.isValid);
                    },
                    onClick: function(resolve, reject) {
                        // for paypal add a workaround, remove when component fixes it
                        if (selectedAlternativePaymentMethodType() === 'paypal') {
                            return self.validate();
                        } else {
                            if (self.validate()) {
                                resolve();
                            } else {
                                reject();
                            }
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
            if (paymentMethod.methodIdentifier in paymentMethodsExtraInfo && 'configuration' in paymentMethodsExtraInfo[paymentMethod.methodIdentifier]) {
                configuration = Object.assign(configuration, paymentMethodsExtraInfo[paymentMethod.methodIdentifier].configuration);
            }

            // Extra apple pay configuration
            if (paymentMethod.methodIdentifier.includes('applepay')) {
                if ('configuration' in configuration &&
                    'merchantName' in configuration.configuration) {
                    configuration.totalPriceLabel = configuration.configuration.merchantName;
                }
            }
            // Extra amazon pay configuration first call to amazon page
            if (paymentMethod.methodIdentifier.includes('amazonpay')) {
                configuration.productType = 'PayAndShip';
                configuration.checkoutMode = 'ProcessOrder';
                configuration.returnUrl = location.href;

                if (formattedShippingAddress &&
                    formattedShippingAddress.telephone) {
                    configuration.addressDetails = {
                        name: formattedShippingAddress.firstName +
                            ' ' +
                            formattedShippingAddress.lastName,
                        addressLine1: formattedShippingAddress.street,
                        addressLine2: formattedShippingAddress.houseNumber,
                        city: formattedShippingAddress.city,
                        postalCode: formattedShippingAddress.postalCode,
                        countryCode: formattedShippingAddress.country,
                        phoneNumber: formattedShippingAddress.telephone
                    };
                }
            }

            return configuration;
        },
        selectPaymentMethodType: function() {
            var self = this;
            $('#stateData').val('');
            let stateData;
            if (!('component' in self)) {
                let paymentMethod = self.paymentMethod;
                stateData = {
                    paymentMethod: {
                        type: paymentMethod.type
                    }
                };
                if ('brand' in paymentMethod) {
                    stateData.paymentMethod.brand = paymentMethod.brand;
                }
                $('#stateData').val(JSON.stringify(stateData));
            }
            return this._super();
        },
    });
});
