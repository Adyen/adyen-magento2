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
    ],
    function (_, quote, customer, urlBuilder, storage, adyenComponent) {
        'use strict';
        var checkoutComponent = {};
        return {
            /**
             * Retrieve the list of available payment methods from the server
             */
            retrieveAvailablePaymentMethods: function () {
                var self = this;

                // retrieve payment methods
                var serviceUrl,
                    payload;
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/retrieve-adyen-payment-methods', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/retrieve-adyen-payment-methods', {
                        cartId: quote.getQuoteId()
                    });
                }

                payload = {
                    cartId: quote.getQuoteId(),
                    shippingAddress: quote.shippingAddress()
                };

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    true
                )
            },
            /**
             * The results that the 3DS2 components returns in the onComplete callback needs to be sent to the
             * backend to the /adyen/threeDS2Process endpoint and based on the response render a new threeDS2
             * component or place the order (validateThreeDS2OrPlaceOrder)
             * @param response
             */
            processThreeDS2: function (data) {
                var payload = {
                    "payload": JSON.stringify(data)
                };

                var serviceUrl = urlBuilder.createUrl('/adyen/threeDS2Process', {});

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    true
                );
            },
            getOrderPaymentStatus: function (orderId) {
                var serviceUrl = urlBuilder.createUrl('/adyen/orders/:orderId/payment-status', {
                    orderId: orderId
                });

                return storage.get(serviceUrl);
            },
            initCheckoutComponent: function(paymentMethodsResponse, originKey, locale, environment, ) {
                checkoutComponent = new AdyenCheckout({
                    locale: locale,
                    originKey: originKey,
                    environment: environment,
                    paymentMethodsResponse: paymentMethodsResponse,
                });
            },
            getCheckoutComponent: function() {
                return checkoutComponent;
            }
        };
    }
);
