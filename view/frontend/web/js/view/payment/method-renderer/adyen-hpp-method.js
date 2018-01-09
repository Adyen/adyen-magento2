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
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/storage',
        'Magento_Checkout/js/model/url-builder',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
    ],
    function (ko, $, Component, selectPaymentMethodAction, quote, checkoutData, additionalValidators, storage, urlBuilder, adyenPaymentService, customer, fullScreenLoader, placeOrderAction) {
        'use strict';
        var brandCode = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var dfValue = ko.observable(null);

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/hpp-form',
                brandCode: ''
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'brandCode',
                        'issuerId',
                        'gender',
                        'dob',
                        'telephone',
                        'dfValue'
                    ]);
                return this;
            },
            initialize: function () {
                var self = this;
                this._super();

                fullScreenLoader.startLoader();

                // reset variable:
                adyenPaymentService.setPaymentMethods();

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
                        function waitForDfSet() {
                            // Wait for dfSet function to be loaded from df.js script
                            if (typeof dfSet == "undefined") {
                                setTimeout(waitForDfSet, 500);
                                return;
                            }

                            // set device fingerprint value
                            dfSet('dfValue', 0);
                            // propagate this manually to knockoutjs otherwise it would not work
                            dfValue($('#dfValue').val());
                        }

                        adyenPaymentService.setPaymentMethods(response);
                        if (JSON.stringify(response).indexOf("ratepay") > -1) {
                            var ratePayId = window.checkoutConfig.payment.adyenHpp.ratePayId;
                            var dfValueRatePay = self.getRatePayDeviceIdentToken();

                            window.di = {
                                t: dfValueRatePay.replace(':', ''),
                                v: ratePayId,
                                l: 'Checkout'
                            };

                            // Load Ratepay script
                            var ratepayScriptTag = document.createElement('script');
                            ratepayScriptTag.src = "//d.ratepay.com/" + ratePayId + "/di.js";
                            ratepayScriptTag.type = "text/javascript";
                            document.body.appendChild(ratepayScriptTag);
                        }

                        // Load Adyen df.js script
                        var dfScriptTag = document.createElement('script');
                        dfScriptTag.src = "//live.adyen.com/hpp/js/df.js?v=20171130";
                        dfScriptTag.type = "text/javascript";
                        document.body.appendChild(dfScriptTag);
                        waitForDfSet();

                        fullScreenLoader.stopLoader();
                    }
                ).fail(function (error) {
                    console.log(JSON.stringify(error));
                    fullScreenLoader.stopLoader();
                });
            },
            getAdyenHppPaymentMethods: function () {
                var self = this;
                var paymentMethods = adyenPaymentService.getAvailablePaymentMethods();

                var paymentList = _.map(paymentMethods, function (value) {
                    var result = {};
                    result.value = value.brandCode;
                    result.name = value;
                    result.method = self.item.method;
                    result.getCode = function () {
                        return self.item.method;
                    };
                    result.validate = function () {
                        return self.validate();
                    };
                    result.placeRedirectOrder = function placeRedirectOrder(data) {
                        return self.placeRedirectOrder(data);
                    };
                    result.isPlaceOrderActionAllowed = function(bool) {
                        return self.isPlaceOrderActionAllowed(bool);
                    };
                    result.afterPlaceOrder = function() {
                        return self.afterPlaceOrder();
                    };
                    result.isPaymentMethodOpenInvoiceMethod = function () {
                        return value.isPaymentMethodOpenInvoiceMethod;
                    };
                    result.getSsnLength = function () {
                        if (quote.billingAddress().countryId == "NO") {
                            //5 digits for Norway
                            return 5;
                        }
                        else {
                            //4 digits for other Nordic countries
                            return 4;
                        }
                    };
                    if (value.brandCode == "ideal") {
                        result.issuerIds = value.issuers;
                        result.issuerId = ko.observable(null);
                    } else if (value.isPaymentMethodOpenInvoiceMethod) {
                        result.telephone = ko.observable(quote.shippingAddress().telephone);
                        result.gender = ko.observable(window.checkoutConfig.payment.adyenHpp.gender);
                        result.dob = ko.observable(window.checkoutConfig.payment.adyenHpp.dob);
                        result.datepickerValue = ko.observable(); // needed ??
                        result.ssn = ko.observable();

                        result.getRatePayDeviceIdentToken = function () {
                            return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
                        };
                        result.showGender = function () {
                            return window.checkoutConfig.payment.adyenHpp.showGender;
                        };
                        result.showDob = function () {
                            return window.checkoutConfig.payment.adyenHpp.showDob;
                        };
                        result.showTelephone = function () {
                            return window.checkoutConfig.payment.adyenHpp.showTelephone;
                        };
                        result.showSsn = function () {
                            if (value.brandCode.indexOf("klarna") >= 0) {
                                var ba = quote.billingAddress();
                                if (ba != null) {
                                    var nordicCountriesList = window.checkoutConfig.payment.adyenHpp.nordicCountries;
                                    if (nordicCountriesList.indexOf(ba.countryId) >= 0) {
                                        return true;
                                    }
                                }
                            }
                            return false;
                        };
                    }
                    return result;
                });
                return paymentList;
            },
            getGenderTypes: function () {
                return _.map(window.checkoutConfig.payment.adyenHpp.genderTypes, function (value, key) {
                    return {
                        'key': key,
                        'value': value
                    }
                });
            },
            /** Redirect to adyen */
            continueToAdyen: function () {
                var self = this;

                if (this.validate() && additionalValidators.validate()) {
                     var data = {};
                    data.method = self.method;
                    this.placeRedirectOrder(data);
                    return false;
                }
            },
            continueToAdyenBrandCode: function () {
                // set payment method to adyen_hpp
                var self = this;

                if (this.validate() && additionalValidators.validate()) {

                    var data = {};
                    data.method = self.method;

                    var additionalData = {};
                    additionalData.brand_code = self.value;
                    additionalData.df_value = dfValue();

                    if (brandCode() == "ideal") {
                        additionalData.issuer_id = this.issuerId();
                    } else if (self.isPaymentMethodOpenInvoiceMethod()) {
                        additionalData.gender = this.gender();
                        additionalData.dob = this.dob();
                        additionalData.telephone = this.telephone();
                        additionalData.ssn = this.ssn();
                        if (brandCode() == "ratepay") {
                            additionalData.df_value = this.getRatePayDeviceIdentToken();
                        }
                    }

                    data.additional_data = additionalData;
                    this.placeRedirectOrder(data);
                }

                return false;
            },
            selectPaymentMethodBrandCode: function () {
                var self = this;

                // set payment method to adyen_hpp
                var data = {
                    "method": self.method,
                    "po_number": null,
                    "additional_data": {
                        brand_code: self.value
                    }
                };

                // set the brandCode
                brandCode(self.value);

                // set payment method
                paymentMethod(self.method);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },
            placeRedirectOrder: function(data) {
                // Place Order but use our own redirect url after
                var self = this;
                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();

                $.when(
                    placeOrderAction(data, this.messageContainer)
                ).fail(
                    function () {
                        self.isPlaceOrderActionAllowed(true);
                    }
                ).done(
                    function () {
                        self.afterPlaceOrder();
                        $.mage.redirect(
                            window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl
                        );
                    }
                )
            },
            isBrandCodeChecked: ko.computed(function () {

                if (!quote.paymentMethod()) {
                    return null;
                }

                if (quote.paymentMethod().method == paymentMethod()) {
                    return brandCode();
                }
                return null;
            }),
            isPaymentMethodSelectionOnAdyen: function () {
                return window.checkoutConfig.payment.adyenHpp.isPaymentMethodSelectionOnAdyen;
            },
            isIconEnabled: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            validate: function () {
                return true;
            },
            getRatePayDeviceIdentToken: function () {
                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
            }
        });
    }
);
