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
            paymentMethods: ko.observable({}),

            /**
             * Retrieve the list of available payment methods from Adyen
             */
            retrievePaymentMethods: function() {
                // url for guest users
                var serviceUrl = urlBuilder.createUrl(
                    '/internal/guest-carts/:cartId/retrieve-adyen-payment-methods', {
                        cartId: quote.getQuoteId(),
                    });

                // url for logged in users
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl(
                        '/internal/carts/mine/retrieve-adyen-payment-methods', {});
                }

                // Construct payload for the retrieve payment methods request
                var payload = {
                    cartId: quote.getQuoteId(),
                    shippingAddress: quote.shippingAddress(),
                    form_key: $.mage.cookies.get('form_key')
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
                var serviceUrl = urlBuilder.createUrl('/internal/adyen/orders/payment-status', {});
                var payload = {
                    orderId: orderId,
                    form_key: $.mage.cookies.get('form_key')
                }
                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    true
                );
            },
            /**
             * The results that the components returns in the onComplete callback needs to be sent to the
             * backend to the /adyen/paymentDetails endpoint and based on the response render a new
             * component or place the order (validateThreeDS2OrPlaceOrder)
             */
            paymentDetails: function(data) {
                var payload = {
                    'payload': JSON.stringify(data),
                    form_key: $.mage.cookies.get('form_key')
                };

                var serviceUrl = urlBuilder.createUrl('/internal/adyen/paymentDetails',
                    {});

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
            }
        };
    }
);
