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
            connectedTerminals: ko.observable(null),

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

            getConnectedTerminals: function() {
                return this.connectedTerminals;
            },

            setConnectedTerminals: function(connectedTerminals) {
                this.connectedTerminals(connectedTerminals);
            },

            getOrderPaymentStatus: function(orderId, quoteId = null) {
                let serviceUrl;

                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/adyen/orders/carts/mine/payment-status', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/adyen/orders/guest-carts/:cartId/payment-status', {
                        cartId: quoteId ?? quote.getQuoteId()
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

            paymentDetails: function(data, orderId, isMultishipping = false) {
                let serviceUrl;
                let payload = {
                    'payload': JSON.stringify(data),
                    'orderId': orderId
                };

                if (customer.isLoggedIn() || isMultishipping) {
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

            donate: function (data, isLoggedIn, orderId, maskedQuoteId) {
                let serviceUrl;
                let request = {
                    payload: JSON.stringify(data)
                };

                if (isLoggedIn) {
                    serviceUrl =  urlBuilder.createUrl('/adyen/orders/carts/mine/donations', {});
                    request.orderId = orderId;
                } else {
                    serviceUrl =  urlBuilder.createUrl('/adyen/orders/guest-carts/:cartId/donations', {
                        cartId: maskedQuoteId
                    });
                }

                return storage.post(
                    serviceUrl,
                    JSON.stringify(request),
                    true
                );
            },

            posPayment: function (orderId) {
                let url = urlBuilder.createUrl('/adyen/orders/carts/mine/pos-payment', {})
                if (!customer.isLoggedIn()) {
                    url = urlBuilder.createUrl(
                        '/adyen/orders/guest-carts/:cartId/pos-payment',
                        {cartId: quote.getQuoteId()}
                    )
                }
                const payload = JSON.stringify({orderId})

                return storage.post(url, payload, true)
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
                let urlPath = '/adyen/guest-carts/:cartId/state-data';
                let urlParams = {cartId: quote.getQuoteId()};

                if (customer.isLoggedIn()) {
                    urlPath = '/adyen/carts/mine/state-data';
                    urlParams = {};
                }

                let serviceUrl = urlBuilder.createUrl(urlPath, urlParams);
                let request = {
                    stateData: JSON.stringify(stateData)
                };

                return storage.post(
                    serviceUrl,
                    JSON.stringify(request),
                );
            },

            removeStateData: function (stateDataId) {
                let urlPath = '/adyen/guest-carts/:cartId/state-data/:stateDataId';
                let urlParams = {cartId: quote.getQuoteId(), stateDataId: stateDataId};

                if (customer.isLoggedIn()) {
                    urlPath = '/adyen/carts/mine/state-data/:stateDataId';
                    urlParams = {stateDataId: stateDataId};
                }

                let serviceUrl = urlBuilder.createUrl(urlPath, urlParams);

                return storage.delete(
                    serviceUrl,
                    JSON.stringify({}),
                );
            },

            fetchRedeemedGiftcards: function () {
                let urlPath = '/adyen/giftcards/guest-carts/:cartId';
                let urlParams = {cartId: quote.getQuoteId()};

                if (customer.isLoggedIn()) {
                    urlPath = '/adyen/giftcards/mine';
                    urlParams = {};
                }

                let serviceUrl = urlBuilder.createUrl(urlPath, urlParams);

                return storage.get(
                    serviceUrl,
                    JSON.stringify({}),
                );
            }
        };
    }
);