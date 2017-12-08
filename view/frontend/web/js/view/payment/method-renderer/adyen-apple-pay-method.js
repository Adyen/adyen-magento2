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
        'underscore',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Payment/js/view/payment/cc-form',
        'Adyen_Payment/js/action/place-order',
        'mage/translate',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
    ],
    function (_, $, quote, Component, placeOrderAction, $t, additionalValidators, urlBuilder, storage) {
        'use strict';
        var billingAddress = quote.billingAddress();
        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/apple-pay-form',
                country: billingAddress.countryId
            },
            initObservable: function () {
                this._super()
                    .observe([]);
                return this;
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
                var request = {
                    countryCode: 'US',
                    currencyCode: 'EUR',
                    supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
                    merchantCapabilities: ['supports3DS'],
                    total: { label: 'Total', amount: quote.totals().grand_total }
                };
                var session = new ApplePaySession(2, request);
                session.onvalidatemerchant = function(event) {
                    var promise = self.performValidation(event.validationURL);
                    promise.then(function (merchantSession) {
                        session.completeMerchantValidation(merchantSession);
                    });
                }

                session.onpaymentauthorized = function(event)
                {
                    var data = {
                        'method': self.item.method,
                        'additional_data': {'token': JSON.stringify(event.payment)}
                    };
                    var redirect = this.redirectAfterPlaceOrder;
                    var promise = self.sendPayment(event.payment, data, redirect);

                    promise.then(function(success) {
                        var status;
                        if(success)
                            status = ApplePaySession.STATUS_SUCCESS;
                        else
                            status = ApplePaySession.STATUS_FAILURE;

                        session.completePayment(status);

                        if(success) {
                            // redirect to success page
                            window.location="/checkout/onepage/success";
                        }
                    }, function(reason) {
                        if(reason.message == "ERROR BILLING") {
                            var status = session.STATUS_INVALID_BILLING_POSTAL_ADDRESS;
                        } else if(reason.message == "ERROR SHIPPING") {
                            var status = session.STATUS_INVALID_SHIPPING_POSTAL_ADDRESS;
                        } else {
                            var status = session.STATUS_FAILURE;
                        }
                        session.completePayment(status);
                    });
                }

                session.begin();
                // if (this.validate() && additionalValidators.validate()) {
                //     this.isPlaceOrderActionAllowed(false);
                //     placeOrder = placeOrderAction(this.getData(), this.redirectAfterPlaceOrder);
                //
                //     $.when(placeOrder).fail(function (response) {
                //         self.isPlaceOrderActionAllowed(true);
                //     });
                //     return true;
                // }
                // return false;
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
                if (window.ApplePaySession) {
                    return true;
                }
                return false;
            },
            performValidation: function(validationURL){
            // Return a new promise.
                return new Promise(function(resolve, reject) {

                    // retrieve payment methods
                    var serviceUrl = urlBuilder.createUrl('/adyen/request-merchant-session', {});

                    storage.post(
                        serviceUrl, JSON.stringify('{}')
                    ).done(
                        function (response){
                            var data = JSON.parse(response);
                            resolve(data);
                        }
                    ).fail(function (error) {
                        console.log(JSON.stringify(error));
                        reject(Error("Network Error"));
                    });
                });
            },
            sendPayment: function(payment, data, redirect) {
                return new Promise(function(resolve, reject) {

                    debugger;;

                    var placeOrder = placeOrderAction(data, redirect);

                    $.when(placeOrder).fail(function(response) {
                        // self.isPlaceOrderActionAllowed(true);
                        reject(Error(response));
                    }).success(function(response) {
                        resolve(true);
                    });
                });
            }
        });
    }
);
