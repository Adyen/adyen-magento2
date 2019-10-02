/*
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
 * Copyright (c) 2017 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

define(
    [
        'jquery',
        'ko',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'mage/url',
        'Magento_Ui/js/model/messages',
        'mage/translate',
    ],
    function ($, ko, quote, Component, placeOrderAction, additionalValidators, urlBuilder, storage, url, Messages, $t) {
        'use strict';
        var canMakeApplePayPayments = ko.observable(false);
        var applePayVersion = 6;
        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/apple-pay-form'
            },

            /**
             * @returns {Boolean}
             */
            isShowLegend: function () {
                return true;
            },
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getCode: function () {
                return 'adyen_apple_pay';
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {}
                };
            },
            isActive: function () {
                return true;
            },
            /**
             * @override
             */
            placeApplePayOrder: function (data, event) {
                event.preventDefault();
                var self = this;
                if (!additionalValidators.validate()) {
                    return false;
                }
                var request = {
                    countryCode: quote.billingAddress().countryId,
                    currencyCode: quote.totals().quote_currency_code,
                    supportedNetworks: ['visa', 'masterCard', 'amex', 'discover', 'maestro', 'vPay', 'jcb', 'elo'],
                    merchantCapabilities: ['supports3DS'],
                    total: {label: $t('Grand Total'), amount: quote.totals().base_grand_total}
                };
                var session = new ApplePaySession(applePayVersion, request);

                session.onvalidatemerchant = function (event) {
                    var promise = self.performValidation(event.validationURL);
                    promise.then(function (merchantSession) {
                        session.completeMerchantValidation(merchantSession);
                    });
                }

                session.onpaymentauthorized = function (event) {
                    var data = {
                        'method': self.item.method,
                        'additional_data': {'token': JSON.stringify(event.payment)}
                    };
                    var promise = self.sendPayment(event.payment, data);

                    promise.then(function (success) {
                        var status;
                        if (success)
                            status = ApplePaySession.STATUS_SUCCESS;
                        else
                            status = ApplePaySession.STATUS_FAILURE;

                        session.completePayment(status);

                        if (success) {
                            window.location.replace(url.build(window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl));
                        }
                    }, function (reason) {
                        if (reason.message == "ERROR BILLING") {
                            var status = session.STATUS_INVALID_BILLING_POSTAL_ADDRESS;
                        } else if (reason.message == "ERROR SHIPPING") {
                            var status = session.STATUS_INVALID_SHIPPING_POSTAL_ADDRESS;
                        } else {
                            var status = session.STATUS_FAILURE;
                        }
                        session.completePayment(status);
                    });
                }

                session.begin();
            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            context: function () {
                return this;
            },
            validate: function () {
                return true;
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            isApplePayAllowed: function () {
                var self = this;

                if (!!window.ApplePaySession) {
                    // validate if applepay is allowed, it will be picked up by the isApplePayVisible method
                    var promise = window.ApplePaySession.canMakePaymentsWithActiveCard(self.getMerchantIdentifier());
                    promise.then(function (canMakePayments) {
                        if (canMakePayments)
                            canMakeApplePayPayments(true);
                    });

                    if (window.ApplePaySession && window.ApplePaySession.supportsVersion(applePayVersion)) {
                        return true;
                    }
                }

                return false;
            },
            performValidation: function (validationURL) {
                // Return a new promise.
                return new Promise(function (resolve, reject) {

                    // retrieve payment methods
                    var serviceUrl = urlBuilder.createUrl('/adyen/request-merchant-session', {});

                    storage.post(
                        serviceUrl, JSON.stringify('{}')
                    ).done(
                        function (response) {
                            var data = JSON.parse(response);
                            resolve(data);
                        }
                    ).fail(function (error) {
                        console.log(JSON.stringify(error));
                        reject(Error("Network Error"));
                    });
                });
            },
            sendPayment: function (payment, data) {
                var deferred = $.Deferred();
                return $.when(
                    placeOrderAction(data, new Messages())
                ).fail(
                    function (response) {
                        deferred.reject(Error(response));
                    }
                ).done(
                    function () {
                        deferred.resolve(true);
                    }
                );
            },
            isApplePayVisible: function () {
                return canMakeApplePayPayments();
            },
            getMerchantIdentifier: function () {
                return window.checkoutConfig.payment.adyen_apple_pay.merchant_identifier;
            }
        });
    }
);
