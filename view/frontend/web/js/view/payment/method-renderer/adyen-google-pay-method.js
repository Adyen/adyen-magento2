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
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/url',
        'Magento_Vault/js/view/payment/vault-enabler',
        'adyenCheckout'
    ],
    function (ko, $, Component, placeOrderAction, additionalValidators, quote, urlBuilder, fullScreenLoader, url, VaultEnabler, AdyenCheckout) {
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
                googlePayToken: null,
                googlePayAllowed: null
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
            isActive: function () {
                return true;
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'googlePayToken',
                        'googlePayAllowed'
                    ]);
                return this;
            }, initialize: function () {
                var self = this;
                this.additionalValidators = additionalValidators;
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
                this.vaultEnabler.isActivePaymentTokenEnabler(false);
                this._super();
            },

            renderGooglePay: function () {
                this.googlePayNode = document.getElementById('googlePay');

                var self = this;
                self.checkoutComponent = new AdyenCheckout({
                    locale: self.getLocale(),
                    originKey: self.getOriginKey(),
                    environment: self.getCheckoutEnvironment(),
                    risk: {
                        enabled: false
                    }
                });
                var googlepay = self.checkoutComponent.create('paywithgoogle', {
                    showPayButton: true,
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

                    // empty onSubmit to resolve javascript issues.
                    onSubmit: function() {},
                    onChange: function (state) {
                        if (!!state.isValid) {
                            self.googlePayToken(state.data.paymentMethod.googlePayToken);
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
                        }
                    },
                    buttonColor: 'black', // default/black/white
                    buttonType: 'long', // long/short
                    showButton: true // show or hide the Google Pay button
                });
                var promise = googlepay.isAvailable();
                promise.then(function (success) {
                    self.googlePayAllowed(true);
                    googlepay.mount(self.googlePayNode);
                    $(self.googlePayNode).find('button').prop('disabled', true);
                }, function (error) {
                    console.log(error);
                    self.googlePayAllowed(false);
                });
            },
            isGooglePayAllowed: function () {
                if (this.googlePayAllowed()) {
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
            validate: function (hideErrors) {
                return this.additionalValidators.validate(hideErrors);
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
            },
            isVaultEnabled: function () {
                return this.vaultEnabler.isVaultEnabled();
            },
            getVaultCode: function () {
                return "adyen_google_pay_vault";
            },
            getOriginKey: function () {
                return window.checkoutConfig.payment.adyen.originKey;
            },
            getCheckoutEnvironment: function () {
                return window.checkoutConfig.payment.adyenGooglePay.checkoutEnvironment;
            },
            onPaymentMethodContentChange: function (data, event) {
                $(this.googlePayNode).find('button').prop('disabled', !this.validate());
            }
        });
    }
);
