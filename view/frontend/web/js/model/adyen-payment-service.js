/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'underscore',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Adyen_Payment/js/bundle',
        'ko'
    ],
    function(
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
                    '/guest-carts/:cartId/retrieve-adyen-payment-methods', {
                        cartId: quote.getQuoteId(),
                    });

                // url for logged in users
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl(
                        '/carts/mine/retrieve-adyen-payment-methods', {});
                }

                // Construct payload for the retrieve payment methods request
                var payload = {
                    cartId: quote.getQuoteId(),
                    shippingAddress: quote.shippingAddress(),
                };

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                );
            },
            getPaymentMethods: function() {
                return this.paymentMethods;
            },
            setPaymentMethods: function(paymentMethods) {
                this.paymentMethods(paymentMethods);
            },
            getOrderPaymentStatus: function(orderId) {
                var serviceUrl = urlBuilder.createUrl(
                    '/adyen/orders/:orderId/payment-status', {
                        orderId: orderId,
                    });

                return storage.get(serviceUrl);
            },
            /**
             * The results that the components returns in the onComplete callback needs to be sent to the
             * backend to the /adyen/paymentDetails endpoint and based on the response render a new
             * component or place the order (validateThreeDS2OrPlaceOrder)
             * @param response
             */
            paymentDetails: function(data) {
                var payload = {
                    'payload': JSON.stringify(data),
                };

                var serviceUrl = urlBuilder.createUrl('/adyen/paymentDetails',
                    {});

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    true,
                );
            }
        };
    },
);
