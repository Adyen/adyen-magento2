/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'jquery',
        'underscore',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Adyen_Payment/js/adyen',
        'ko',
        'mage/cookies'
    ],
    function(
        $,
        _,
        quote,
        customer,
        urlBuilder,
        storage,
        adyenComponent,
        ko
    ) {
        'use strict';
        return {
            paymentMethods: ko.observable(null),

            /**
             * Retrieve the list of available payment methods from Adyen
             */
            retrievePaymentMethods: function() {
                // url for guest users
                let serviceUrl = urlBuilder.createUrl(
                    '/guest-carts/:cartId/retrieve-adyen-payment-methods', {
                        cartId: quote.getQuoteId()
                    });

                // url for logged in users
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/retrieve-adyen-payment-methods', {});
                }

                // Construct payload for the retrieve payment methods request
                let payload = {
                    cartId: quote.getQuoteId(),
                    country: quote.billingAddress().countryId
                };

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                );
            },

            getPaymentMethods: function() {
                return this.paymentMethods;
            },

            setPaymentMethods: function(paymentMethods) {
                this.paymentMethods(paymentMethods);
            },

            getOrderPaymentStatus: function(orderId) {
                let serviceUrl;

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/adyen/orders/carts/mine/payment-status', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/adyen/orders/guest-carts/:cartId/payment-status', {
                        cartId: quote.getQuoteId()
                    });
                }

                let payload = {
                    orderId: orderId
                }
                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    true
                );
            },

            paymentDetails: function(data, orderId) {
                let serviceUrl;
                let payload = {
                    'payload': JSON.stringify(data),
                    'orderId': orderId
                };

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl(
                        '/adyen/carts/mine/payments-details',
                        {}
                    );
                } else {
                    serviceUrl = urlBuilder.createUrl(
                        '/adyen/guest-carts/:cartId/payments-details', {
                            cartId: quote.getQuoteId(),
                        }
                    );
                }

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    true
                );
            },

            donate: function (data) {
                let request = {
                    payload: JSON.stringify(data),
                    formKey: $.mage.cookies.get('form_key')
                };

                const serviceUrl = urlBuilder.createUrl('/internal/adyen/donations', {});
 
                return storage.post(
                    serviceUrl,
                    JSON.stringify(request),
                    true
                );
            },

            getPaymentMethodFromResponse: function (txVariant, paymentMethodResponse) {
                return paymentMethodResponse.find((paymentMethod) => {
                    return txVariant === paymentMethod.type
                });
            },

            paymentMethodsBalance: function (payload) {
                let serviceUrl = urlBuilder.createUrl('/adyen/payment-methods/balance', {});

                let request = {
                    payload: JSON.stringify(payload)
                };

                return storage.post(
                    serviceUrl,
                    JSON.stringify(request),
                );
            },

            saveStateData: function (stateData) {
                let serviceUrl;

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/adyen/carts/mine/state-data', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/adyen/guest-carts/:cartId/state-data', {
                        cartId: quote.getQuoteId()
                    });
                }

                let request = {
                    stateData: JSON.stringify(stateData)
                };

                return storage.post(
                    serviceUrl,
                    JSON.stringify(request),
                );
            },

            removeStateData: function (stateDataId) {
                let serviceUrl;

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/adyen/carts/mine/state-data/:stateDataId', {
                        stateDataId: stateDataId
                    });
                } else {
                    serviceUrl = urlBuilder.createUrl('/adyen/guest-carts/:cartId/state-data/:stateDataId', {
                        cartId: quote.getQuoteId(),
                        stateDataId: stateDataId
                    });
                }

                let request = {};

                return storage.delete(
                    serviceUrl,
                    JSON.stringify(request),
                );
            },

            fetchRedeemedGiftcards: function () {
                let serviceUrl;

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/adyen/giftcards/mine', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/adyen/giftcards/guest-carts/:cartId', {
                        cartId: quote.getQuoteId()
                    });
                }

                let request = {};

                return storage.get(
                    serviceUrl,
                    JSON.stringify(request),
                );
            }
        };
    }
);
