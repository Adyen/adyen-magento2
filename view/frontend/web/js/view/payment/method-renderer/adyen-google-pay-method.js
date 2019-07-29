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
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function (ko, $, Component, additionalValidators, placeOrderAction, quote, agreementsAssigner, customer, urlBuilder, storage, fullScreenLoader, errorProcessor, Messages, redirectOnSuccessAction) {
        'use strict';

        /**
         * Shareble adyen checkout component
         * @type {AdyenCheckout}
         */
        var checkoutComponent;

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/google-pay-form'
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
                        'brandCode',
                        'issuer',
                        'gender',
                        'dob',
                        'telephone',
                        'ownerName',
                        'ibanNumber',
                        'ssn',
                        'bankAccountNumber',
                        'bankLocationId'
                    ]);
                return this;
            },initialize: function () {
                var self = this;
                this._super();

            },

            renderGooglePay: function() {
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
                    // environment: 'PRODUCTION',
                    environment: self.getCheckoutEnvironment().toUpperCase(),

                    configuration: {
                        // Adyen's merchant account
                        gatewayMerchantId: 'MagentoMerchantAlessio2',

                        // https://developers.google.com/pay/api/web/reference/object#MerchantInfo
                        merchantIdentifier: '123123123123123',
                        merchantName: 'MagentoMerchantAlessio2'
                    },

                    // Payment
                    amount: self.formatAmount(quote.totals().grand_total, quote.totals().quote_currency_code),
                    currency: quote.totals().quote_currency_code,
                    totalPriceStatus: 'FINAL',

                    //     // Callbacks
                    onError: function (error) {
                        console.log("err");
                        console.log(error)
                    },
                    onAuthorized: function (state) {
                        console.log("onauth");

                    },
                    onChange: function (state) {
                        console.log("onchange");
                        console.log(state);
                        if (!!state.isValid) {
                            var data = {
                                'method': "adyen_google_pay",
                                'additional_data': {
                                    'token': state.data.paymentMethod["paywithgoogle.token"]
                        }
                        }
                            ;
                            self.isPlaceOrderActionAllowed(true);
                            console.log(data);
                                placeOrderAction(data, new Messages())
                        }
                        console.log(state);
                    },
                    // ButtonOptions
                    // https://developers.google.com/pay/api/web/reference/object#ButtonOptions
                    buttonColor: 'default', // default/black/white
                    buttonType: 'short', // long/short
                    showButton: true, // show or hide the Google Pay button

                    // CardParameters
                    // https://developers.google.com/pay/api/web/reference/object#CardParameters
                    allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                    allowedCardNetworks: ['AMEX', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA'],
                    existingPaymentMethodRequired: true,
                    allowPrepaidCards: true, // Set to false if you don't support prepaid cards.
                    billingAddressRequired: false, // A billing address should only be requested if it's required to process the transaction.
                    billingAddressParameters: {}, // The expected fields returned if billingAddressRequired is set to true.

                    emailRequired: false,
                    shippingAddressRequired: false,
                    shippingAddressParameters: {} // https://developers.google.com/pay/api/web/reference/object#ShippingAddressParameters
            }).mount(googlePayNode);
console.log(googlepay);
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
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            getLocale: function () {
                return window.checkoutConfig.payment.adyenGooglePay.locale;
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
            // /**
            //  * Get available card types translated to the Adyen card type codes
            //  * (The card type alt code is the Adyen card type code)
            //  *
            //  * @returns {string[]}
            //  */
            // getAvailableCardTypeAltCodes: function () {
            //     var ccTypes = window.checkoutConfig.payment.ccform.availableTypesByAlt[this.getCode()];
            //     return Object.keys(ccTypes);
            // }


            /**
             * Return the formatted currency. Adyen accepts the currency in multiple formats.
             * @param $amount
             * @param $currency
             * @return string
             */
            formatAmount: function (amount, currency) {
                switch (currency) {
                    case "CVE":
                    case "DJF":
                    case "GNF":
                    case "IDR":
                    case "JPY":
                    case "KMF":
                    case "KRW":
                    case "PYG":
                    case "RWF":
                    case "UGX":
                    case "VND":
                    case "VUV":
                    case "XAF":
                    case "XOF":
                    case "XPF":
                        var format = 0;
                        break;
                    case "BHD":
                    case "IQD":
                    case "JOD":
                    case "KWD":
                    case "LYD":
                    case "OMR":
                    case "TND":
                        var format = 3;
                        break;
                    default:
                        var format = 2;
                }
                return Math.round(amount * (Math.pow(10, format)))
            }
        });
    }
);
