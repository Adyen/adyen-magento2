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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer) {
        'use strict';

        //return function () {
        //    var serviceUrl,
        //        payload,
        //        paymentData = quote.paymentMethod();
        //
        //    /**
        //     * Checkout for guest and registered customer.
        //     */
        //    if (!customer.isLoggedIn()) {
        //        serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/selected-payment-method', {
        //            cartId: quote.getQuoteId()
        //        });
        //        payload = {
        //            cartId: quote.getQuoteId(),
        //            method: paymentData
        //        };
        //    } else {
        //        serviceUrl = urlBuilder.createUrl('/carts/mine/selected-payment-method', {});
        //        payload = {
        //            cartId: quote.getQuoteId(),
        //            method: paymentData
        //        };
        //    }
        //    return storage.put(
        //        serviceUrl, JSON.stringify(payload)
        //    ).done(
        //        function () {
        //            $.mage.redirect(window.checkoutConfig.payment.adyenHpp.redirectUrl[quote.paymentMethod().method]);
        //        }
        //    ).fail(
        //        function (response) {
        //            errorProcessor.process(response);
        //        }
        //    );
        //};

        return function () {

            var serviceUrl,
                    payload,
                    paymentData = quote.paymentMethod();

            /** Checkout for guest and registered customer. */
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/payment-information', {
                    quoteId: quote.getQuoteId()
                });
                payload = {
                    cartId: quote.getQuoteId(),
                    email: quote.guestEmail,
                    paymentMethod: paymentData,
                    billingAddress: quote.billingAddress()
                };
            } else {
                serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
                payload = {
                    cartId: quote.getQuoteId(),
                    paymentMethod: paymentData,
                    billingAddress: quote.billingAddress()
                };
            }
            return storage.post(
                serviceUrl, JSON.stringify(payload)
            ).done(
                function () {
                    $.mage.redirect(window.checkoutConfig.payment.adyenHpp.redirectUrl[quote.paymentMethod().method]);
                }
            ).fail(
                function (response) {
                    errorProcessor.process(response);
                }
            );
        };
    }
);
