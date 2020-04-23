/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'underscore',
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/model/adyen-method-list',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Adyen_Payment/js/bundle',
    ],
    function (_, quote, methodList, customer, urlBuilder, storage) {
        'use strict';
        var checkoutComponent = {};
        return {
            /**
             * Populate the list of payment methods
             * @param {Array} methods
             */
            setPaymentMethods: function (methods) {
                methodList(methods);
            },
            /**
             * Get the list of available payment methods.
             * @returns {Array}
             */
            getAvailablePaymentMethods: function () {
                return methodList();
            },
            /**
             * Retrieve the list of available payment methods from the server
             */
            retrieveAvailablePaymentMethods: function (callback) {
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

                storage.post(
                    serviceUrl, JSON.stringify(payload)
                ).done(
                    function (response) {
                        var jsonResponse = JSON.parse(response);
                        console.log(jsonResponse);

                        self.setPaymentMethods(jsonResponse);
                        if (callback) {
                            callback();
                        }
                    }
                ).fail(
                    function () {
                        self.setPaymentMethods([]);
                    }
                )
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
