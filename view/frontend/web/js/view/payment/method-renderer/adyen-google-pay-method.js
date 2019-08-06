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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'Magento_CheckoutAgreements/js/model/agreements-assigner',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Ui/js/model/messages',
        'Magento_Checkout/js/action/redirect-on-success',
        'mage/url'
    ],
    function (ko, $, Component, additionalValidators, placeOrderAction, quote, agreementsAssigner, customer, urlBuilder, storage, fullScreenLoader, errorProcessor, Messages, redirectOnSuccessAction, url) {
        'use strict';

        /**
         * Shareble adyen checkout component
         * @type {AdyenCheckout}
         */
        var checkoutComponent;

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/google-pay-form',
                googlePayToken: null
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
                return 'adyen_google_pay';
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
            initObservable: function () {
                this._super()
                    .observe([
                        'googlePayToken'
                    ]);
                return this;
            }, initialize: function () {
                var self = this;
                this._super();

            },

            renderGooglePay: function () {
                var self = this;
                var googlePayNode = document.getElementById('googlePay');
                self.checkoutComponent = new AdyenCheckout({
                    environment: "TEST",
                    locale: self.getLocale(),
                    risk: {
                        enabled: false
                    }
                });
                const googlepay = self.checkoutComponent.create('paywithgoogle', {
                    environment: self.getCheckoutEnvironment().toUpperCase(),

                    configuration: {
                        // Adyen's merchant account
                        gatewayMerchantId: self.getMerchantAccount(),

                        // https://developers.google.com/pay/api/web/reference/object#MerchantInfo
                        merchantIdentifier: self.getMerchantIdentifier(),
                        merchantName: self.getMerchantAccount()
                    },

                    // Payment
                    amount: self.formatAmount(quote.totals().grand_total, self.getFormat()),
                    currency: quote.totals().quote_currency_code,
                    totalPriceStatus: 'FINAL',

                    onChange: function (state) {
                        if (!!state.isValid) {
                            self.googlePayToken(state.data.paymentMethod["paywithgoogle.token"]);
                            self.getPlaceOrderDeferredObject()
                                .fail(
                                    function () {
                                        fullScreenLoader.stopLoader();
                                        self.isPlaceOrderActionAllowed(true);
                                    }
                                ).done(
                                function () {
                                    self.afterPlaceOrder();
                                    window.location.replace(url.build(window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl));

                                }
                            );
                            // placeOrderAction(data, new Messages())
                        }
                    },
                    // ButtonOptions
                    // https://developers.google.com/pay/api/web/reference/object#ButtonOptions
                    buttonColor: 'black', // default/black/white
                    buttonType: 'short', // long/short
                    showButton: true, // show or hide the Google Pay button

                }).mount(googlePayNode);
            },
            getCheckoutEnvironment: function () {
                return window.checkoutConfig.payment.adyenGooglePay.checkoutEnvironment;
            },
            isGooglePayAllowed: function () {
                if (true) {
                    return true;
                }
                return false;
            },
            getMerchantAccount: function () {
                return window.checkoutConfig.payment.adyenGooglePay.merchantAccount;
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            getLocale: function () {
                return window.checkoutConfig.payment.adyenGooglePay.locale;
            },
            getFormat: function () {
                return window.checkoutConfig.payment.adyenGooglePay.format;
            },
            getMerchantIdentifier: function () {
                return window.checkoutConfig.payment.adyenGooglePay.merchantIdentifier;
            },
            context: function () {
                return this;
            },
            validate: function () {
                return true;
            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            /**
             * Get data for place order
             * @returns {{method: *}}
             */
            getData: function () {
                return {
                    'method': "adyen_google_pay",
                    'additional_data': {
                        'token': this.googlePayToken()
                    }
                };
            },

            /**
             * Return the formatted currency. Adyen accepts the currency in multiple formats.
             * @param $amount
             * @param $currency
             * @return string
             */
            formatAmount: function (amount, format) {
                return Math.round(amount * (Math.pow(10, format)))
            }
        });
    }
);
