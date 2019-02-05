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
        'uiLayout',
        'Magento_Ui/js/model/messages'
    ],
    function (ko, $, Component, selectPaymentMethodAction, quote, checkoutData, additionalValidators, storage, urlBuilder, adyenPaymentService, customer, fullScreenLoader, placeOrderAction, layout, Messages) {
        'use strict';
        var brandCode = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var dfValue = ko.observable(null);
        var messageComponents;
        /**
         * Shareble adyen checkout component
         * @type {AdyenCheckout}
         */
        var checkoutComponent;

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
                        'dfValue',
                        'ownerName',
                        'ibanNumber'
                    ]);
                return this;
            },initialize: function () {

                var self = this;
                this._super();

                fullScreenLoader.startLoader();

                /**
                 * Create sherable checkout component
                 * @type {AdyenCheckout}
                 */
                self.checkoutComponent = new AdyenCheckout({
                    locale: self.getLocale()
                });

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

                        // create component needs to be in initialize method
                        var messageComponents = {};
                        _.map(response, function (value) {

                            var messageContainer = new Messages();
                            var name = 'messages-' + self.getBrandCodeFromPaymentMethod(value);
                            var messagesComponent = {
                                parent: self.name,
                                name: 'messages-' + self.getBrandCodeFromPaymentMethod(value),
                                displayArea: 'messages-' + self.getBrandCodeFromPaymentMethod(value),
                                component: 'Magento_Ui/js/view/messages',
                                config: {
                                    messageContainer: messageContainer
                                }
                            };
                            layout([messagesComponent]);

                            messageComponents[name] = messageContainer;
                        });
                        self.messageComponents = messageComponents;

                        fullScreenLoader.stopLoader();
                    }
                ).fail(function (error) {
                    fullScreenLoader.stopLoader();
                });
            },
            getAdyenHppPaymentMethods: function () {
                var self = this;
                var paymentMethods = adyenPaymentService.getAvailablePaymentMethods();

                var paymentList = _.map(paymentMethods, function (value) {
                    var result = {};

                    /**
                     * Returns the payment method's brand code (in checkout api it is the type)
                     * @returns {*}
                     */
                    result.getBrandCode = function() {
                        return self.getBrandCodeFromPaymentMethod(value);
                    };

                    result.value = result.getBrandCode();
                    result.name = value;
                    result.method = self.item.method;
                    /**
                     * Observable to enable and disable place order buttons for payment methods
                     * Default value is true to be able to send the real hpp requiests that doesn't require any input
                     * @type {observable}
                     */
                    result.placeOrderAllowed = ko.observable(true);
                    result.getCode = function () {
                        return self.item.method;
                    };
                    result.validate = function () {
                        return self.validate(result.getBrandCode());
                    };
                    result.placeRedirectOrder = function placeRedirectOrder(data) {
                        return self.placeRedirectOrder(data);
                    };
                    /**
                     * Set and get if the place order action is allowed
                     * Sets the placeOrderAllowed observable and the original isPlaceOrderActionAllowed as well
                     * @param bool
                     * @returns {*}
                     */
                    result.isPlaceOrderAllowed = function(bool) {
                        self.isPlaceOrderActionAllowed(bool);
                        return result.placeOrderAllowed(bool);
                    };
                    result.afterPlaceOrder = function() {
                        return self.afterPlaceOrder();
                    };
                    /**
                     * Checks if payment method is open invoice
                     * @returns {*|isPaymentMethodOpenInvoiceMethod}
                     */
                    result.isPaymentMethodOpenInvoiceMethod = function () {
                        return value.isPaymentMethodOpenInvoiceMethod;
                    };
                    /**
                     * Checks if payment method is open invoice but not in the list below
                     * [klarna, afterpay]
                     * @returns {boolean}
                     */
                    result.isPaymentMethodOtherOpenInvoiceMethod = function () {
                        if (
                            !result.isPaymentMethodAfterPay() &&
                            !result.isPaymentMethodKlarna() &&
                            !result.isPaymentMethodAfterPayTouch() &&
                            value.isPaymentMethodOpenInvoiceMethod
                        ) {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Checks if payment method is klarna
                     * @returns {boolean}
                     */
                    result.isPaymentMethodKlarna = function () {
                        if (result.getBrandCode() === "klarna") {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Checks if payment method is after pay
                     * @returns {boolean}
                     */
                    result.isPaymentMethodAfterPay = function () {
                        if (result.getBrandCode() === "afterpay_default") {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Checks if payment method is after pay touch
                     * @returns {boolean}
                     */
                    result.isPaymentMethodAfterPayTouch = function () {
                        if (result.getBrandCode() === "afterpaytouch") {
                            return true;
                        }

                        return false;
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
                    /**
                     * Checks if the payment method has issuers property available
                     * @returns {boolean}
                     */
                    result.hasIssuersProperty = function () {
                        if (typeof value.details !== 'undefined' && typeof value.details[0].items !== 'undefined') {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Checks if the payment method has issuer(s) available
                     * @returns {boolean}
                     */
                    result.hasIssuersAvailable = function () {
                        if (result.hasIssuersProperty() && value.details[0].items.length > 0) {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Returns the issuers for a payment method
                     * @returns {*}
                     */
                    result.getIssuers = function() {
                        if (result.hasIssuersAvailable()) {
                            return value.details[0].items;
                        }

                        return [];
                    };
                    /**
                     * Checks if payment method is iDeal
                     * @returns {boolean}
                     */
                    result.isIdeal = function () {
                        if (result.getBrandCode().indexOf("ideal") >= 0) {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Checks if payment method is sepa direct debit
                     */
                    result.isSepaDirectDebit = function () {
                        if (result.getBrandCode().indexOf("sepadirectdebit") >= 0) {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Renders the secure fields,
                     * creates the ideal component,
                     * sets up the callbacks for ideal components and
                     */
                    result.renderIdealComponent = function () {
                        result.isPlaceOrderAllowed(false);

                        var idealNode = document.getElementById('iDealContainer');

                        var ideal = self.checkoutComponent.create('ideal', {
                            items: result.getIssuers(),
                            onChange: function (state) {
                                if (!!state.isValid) {
                                    result.issuerId(state.data.issuer);
                                    result.isPlaceOrderAllowed(true);

                                } else {
                                    result.isPlaceOrderAllowed(false);
                                }
                            }
                        });

                        ideal.mount(idealNode);
                    };

                    /**
                     * Creates the sepa direct debit component,
                     * sets up the callbacks for sepa components
                     */
                    result.renderSepaDirectDebitComponent = function () {
                        result.isPlaceOrderAllowed(false);

                        var sepaDirectDebitNode = document.getElementById('sepaDirectDebitContainer');

                        var sepaDirectDebit = self.checkoutComponent.create('sepadirectdebit', {
                            countryCode: self.getLocale(),
                            onChange: function (state) {
                                if (!!state.isValid) {
                                    result.ownerName(state.data["sepa.ownerName"]);
                                    result.ibanNumber(state.data["sepa.ibanNumber"]);
                                    result.isPlaceOrderAllowed(true);
                                } else {
                                    result.isPlaceOrderAllowed(false);
                                }
                            }
                        });

                        sepaDirectDebit.mount(sepaDirectDebitNode);
                    };

                    /**
                     * Creates the klarna component,
                     * sets up the callbacks for klarna components
                     */
                    result.renderKlarnaComponent = function () {

                        var klarnaNode = document.getElementById('klarnaContainer');

                        var klarna = self.checkoutComponent.create('klarna', {
                            countryCode: self.getLocale(),
                            details: self.filterOutOpenInvoiceComponentDetails(value.details),
                            visibility: {
                                personalDetails: "editable"
                            },
                            onChange: function (state) {
                                if (!!state.isValid) {
                                    result.dob(state.data.personalDetails.dateOfBirth);
                                    result.telephone(state.data.personalDetails.telephoneNumber);
                                    result.gender(state.data.personalDetails.gender);
                                    result.isPlaceOrderAllowed(true);
                                } else {
                                    result.isPlaceOrderAllowed(false);
                                }
                            }
                        }).mount(klarnaNode);
                    };

                    /**
                     * Creates the afterpay component,
                     * sets up the callbacks for klarna components
                     */
                    result.renderAfterPayComponent = function () {

                        var afterPay = self.checkoutComponent.create('afterpay', {
                            countryCode: self.getLocale(),
                            details: self.filterOutOpenInvoiceComponentDetails(value.details),
                            visibility: {
                                personalDetails: "editable"
                            },
                            onChange: function (state) {
                                if (!!state.isValid) {
                                    result.dob(state.data.personalDetails.dateOfBirth);
                                    result.telephone(state.data.personalDetails.telephoneNumber);
                                    result.gender(state.data.personalDetails.gender);
                                    result.isPlaceOrderAllowed(true);
                                } else {
                                    result.isPlaceOrderAllowed(false);
                                }
                            }
                        }).mount(document.getElementById('afterPayContainer'));
                    };

                    if (result.hasIssuersProperty()) {
                        if (!result.hasIssuersAvailable()) {
                            return false;
                        }

                        result.issuerIds = result.getIssuers();
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
                        result.showSsn = function () {
                            if (result.getBrandCode().indexOf("klarna") >= 0) {
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
                    } else if (result.isSepaDirectDebit()) {
                        result.ownerName = ko.observable(null);
                        result.ibanNumber = ko.observable(null);
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
                if (this.validate() && additionalValidators.validate()) {
                    this.placeRedirectOrder(this.getData());
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

                    if (self.hasIssuersAvailable()) {
                        additionalData.issuer_id = this.issuerId();
                    } else if (self.isPaymentMethodOpenInvoiceMethod()) {
                        additionalData.gender = this.gender();
                        additionalData.dob = this.dob();
                        additionalData.telephone = this.telephone();
                        additionalData.ssn = this.ssn();
                        if (brandCode() == "ratepay") {
                            additionalData.df_value = this.getRatePayDeviceIdentToken();
                        }
                    } else if (self.isSepaDirectDebit()) {
                        additionalData.ownerName = this.ownerName();
                        additionalData.ibanNumber = this.ibanNumber();
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

                var messageContainer = this.messageContainer;
                if(brandCode()) {
                    messageContainer = self.messageComponents['messages-' + brandCode()];
                }
                
                this.isPlaceOrderActionAllowed(false);
                fullScreenLoader.startLoader();
                $.when(
                    placeOrderAction(data, messageContainer)
                ).fail(
                    function () {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
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
            validate: function (brandCode) {
                var form = '#payment_form_' + this.getCode() + '_' + brandCode;
                var validate =  $(form).validation() && $(form).validation('isValid');

                if(!validate) {
                    return false;
                }

                return true;
            },
            /**
             * Returns the payment method's brand code using the payment method from the response object
             * (in checkout api it is the type)
             * @returns {*}
             */
            getBrandCodeFromPaymentMethod: function (paymentMethod) {
                if (typeof paymentMethod.type !== 'undefined') {
                    return paymentMethod.type;
                }

                return '';
            },
            getRatePayDeviceIdentToken: function () {
                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
            },
            getLocale: function () {
                return window.checkoutConfig.payment.adyenHpp.locale;
            },
            /**
             * In the open invoice components we need to validate only the personal details and only the
             * dateOfBirth, telephoneNumber and gender if it's set in the admin
             * @param details
             * @returns {Array}
             */
            filterOutOpenInvoiceComponentDetails: function (details) {
                var self = this;
                var filteredDetails = _.map(details, function (parentDetail) {
                    if (parentDetail.key == "personalDetails") {
                        var detailObject = _.map(parentDetail.details, function (detail) {
                            if (detail.key == 'dateOfBirth' ||
                                detail.key == 'telephoneNumber' ||
                                detail.key == 'gender') {
                                return detail;
                            }
                        });

                        if (!!detailObject) {
                            return {
                                "key": parentDetail.key,
                                "type": parentDetail.type,
                                "details": self.filterUndefinedItemsInArray(detailObject)
                            };
                        }
                    }
                });

                return self.filterUndefinedItemsInArray(filteredDetails);
            },
            /**
             * Helper function to filter out the undefined items from an array
             * @param arr
             * @returns {*}
             */
            filterUndefinedItemsInArray: function(arr) {
                return arr.filter(function(item){
                    return typeof item !== 'undefined';
                });
            }
        });
    }
);