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
        'Magento_Ui/js/model/messages',
        'Adyen_Payment/js/model/threeds2',
        'Magento_Checkout/js/model/error-processor',
        'adyenCheckout'
    ],
    function (ko, $, Component, selectPaymentMethodAction, quote, checkoutData, additionalValidators, storage, urlBuilder, adyenPaymentService, customer, fullScreenLoader, placeOrderAction, layout, Messages, threeds2, errorProcessor, AdyenCheckout) {
        'use strict';
        var brandCode = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var shippingAddressCountryCode = quote.shippingAddress().countryId;
        var unsupportedPaymentMethods = ['scheme', 'boleto', 'bcmc_mobile_QR', 'wechatpay', /^bcmc$/, "applepay", "paywithgoogle"];
        var popupModal;
        /**
         * Shareble adyen checkout component
         * @type {AdyenCheckout}
         */
        var checkoutComponent;
        var orderId;

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
            }, initialize: function () {


                var self = this;
                this._super();

                fullScreenLoader.startLoader();

                /**
                 * Create sherable checkout component
                 * @type {AdyenCheckout}
                 */
                self.checkoutComponent = new AdyenCheckout({
                    locale: self.getLocale(),
                    onAdditionalDetails: self.handleOnAdditionalDetails.bind(self),
                    originKey: self.getOriginKey(),
                    environment: self.getCheckoutEnvironment()
                });

                // reset variable:
                adyenPaymentService.setPaymentMethods();

                adyenPaymentService.retrieveAvailablePaymentMethods(function () {
                    var paymentMethods = adyenPaymentService.getAvailablePaymentMethods();
                    if (JSON.stringify(paymentMethods).indexOf("ratepay") > -1) {
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

                    fullScreenLoader.stopLoader();
                });
            },
            getAdyenHppPaymentMethods: function () {
                var self = this;
                var currentShippingAddressCountryCode = quote.shippingAddress().countryId;

                // retrieve new payment methods if country code changed
                if (shippingAddressCountryCode != currentShippingAddressCountryCode) {
                    fullScreenLoader.startLoader();
                    adyenPaymentService.retrieveAvailablePaymentMethods();
                    shippingAddressCountryCode = currentShippingAddressCountryCode;
                    fullScreenLoader.stopLoader();
                }

                var paymentMethods = adyenPaymentService.getAvailablePaymentMethods();

                var paymentList = _.reduce(paymentMethods, function (accumulator, value) {

                    if (!self.isPaymentMethodSupported(value.type)) {
                        return accumulator;
                    }

                    var messageContainer = new Messages();
                    var name = 'messages-' + self.getBrandCodeFromPaymentMethod(value);
                    var messagesComponent = {
                        parent: self.name,
                        name: name,
                        displayArea: name,
                        component: 'Magento_Ui/js/view/messages',
                        config: {
                            messageContainer: messageContainer
                        }
                    };
                    layout([messagesComponent]);


                    var result = {};

                    /**
                     * Returns the payment method's brand code (in checkout api it is the type)
                     * @returns {*}
                     */
                    result.getBrandCode = function () {
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
                    result.getMessageName = function () {
                        return 'messages-' + self.getBrandCodeFromPaymentMethod(value)
                    };
                    result.getMessageContainer = function () {
                        return messageContainer;
                    }
                    result.validate = function () {
                        return self.validate(result.getBrandCode());
                    };
                    result.placeRedirectOrder = function placeRedirectOrder(data) {

                        // Place Order but use our own redirect url after
                        fullScreenLoader.startLoader();
                        $('.hpp-message').slideUp();
                        self.isPlaceOrderActionAllowed(false);

                        $.when(
                            placeOrderAction(data, self.currentMessageContainer)
                        ).fail(
                            function (response) {
                                self.isPlaceOrderActionAllowed(true);
                                fullScreenLoader.stopLoader();
                                self.showErrorMessage(response);
                            }
                        ).done(
                            function (orderId) {
                                self.afterPlaceOrder();
                                adyenPaymentService.getOrderPaymentStatus(orderId)
                                    .done(function (responseJSON) {
                                        self.validateActionOrPlaceOrder(responseJSON, orderId);
                                    });
                            }
                        )
                    };

                    /**
                     * Set and get if the place order action is allowed
                     * Sets the placeOrderAllowed observable and the original isPlaceOrderActionAllowed as well
                     * @param bool
                     * @returns {*}
                     */
                    result.isPlaceOrderAllowed = function (bool) {
                        self.isPlaceOrderActionAllowed(bool);
                        return result.placeOrderAllowed(bool);
                    };
                    result.afterPlaceOrder = function () {
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
                    /**
                     * Get personal number (SSN) length based on the buyer's country
                     * @returns {number}
                     */
                    result.getSsnLength = function () {
                        if (quote.billingAddress().countryId == "NO") {
                            //14 digits for Norway ÅÅÅÅMMDD-XXXXX
                            return 14;
                        } else {
                            //13 digits for other Nordic countries ÅÅÅÅMMDD-XXXX
                            return 13;
                        }
                    };
                    /**
                     * Get max length for the Bank account number
                     */
                    result.getBankAccountNumberMaxLength = function () {
                        return 17;
                    };
                    /**
                     * Finds the issuer property in the payment method's response and if available returns it's index
                     * @returns
                     */
                    result.findIssuersProperty = function () {
                        var issuerKey = false;
                        if (typeof value.details !== 'undefined') {
                            $.each(value.details, function (key, detail) {
                                if (typeof detail.items !== 'undefined' && detail.key == 'issuer') {
                                    issuerKey = key;
                                }
                            });
                        }

                        return issuerKey;
                    }
                    /**
                     * Checks if the payment method has issuers property available
                     * @returns {boolean}
                     */
                    result.hasIssuersProperty = function () {
                        if (result.findIssuersProperty() !== false) {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Checks if the payment method has issuer(s) available
                     * @returns {boolean}
                     */
                    result.hasIssuersAvailable = function () {
                        if (result.hasIssuersProperty() && value.details[result.findIssuersProperty()].items.length > 0) {
                            return true;
                        }

                        return false;
                    };
                    /**
                     * Returns the issuers for a payment method
                     * @returns {*}
                     */
                    result.getIssuers = function () {
                        if (result.hasIssuersAvailable()) {
                            return value.details[result.findIssuersProperty()].items;
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
                     * Checks if payment method is ACH
                     * @returns {boolean}
                     */
                    result.isAch = function () {
                        if (result.getBrandCode().indexOf("ach") == 0) {
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
                                    result.issuer(state.data.paymentMethod.issuer);
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
                                    result.ownerName(state.data.paymentMethod["sepa.ownerName"]);
                                    result.ibanNumber(state.data.paymentMethod["sepa.ibanNumber"]);
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

                        /* The new Klarna integration doesn't return details and the component does not handle it */
                        if (!value.details) {
                            return;
                        }

                        var klarnaNode = document.getElementById('klarnaContainer');

                        var klarna = self.checkoutComponent.create('klarna', {
                            countryCode: self.getLocale(),
                            details: self.filterOutOpenInvoiceComponentDetails(value.details),
                            visibility: {
                                personalDetails: "editable"
                            },
                            onChange: function (state) {
                                if (!!state.isValid) {
                                    result.dob(state.data.paymentMethod.personalDetails.dateOfBirth);
                                    result.telephone(state.data.paymentMethod.personalDetails.telephoneNumber);
                                    result.gender(state.data.paymentMethod.personalDetails.gender);
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
                                    result.dob(state.data.paymentMethod.personalDetails.dateOfBirth);
                                    result.telephone(state.data.paymentMethod.personalDetails.telephoneNumber);
                                    result.gender(state.data.paymentMethod.personalDetails.gender);
                                    result.isPlaceOrderAllowed(true);
                                } else {
                                    result.isPlaceOrderAllowed(false);
                                }
                            }
                        }).mount(document.getElementById('afterPayContainer'));
                    };

                    result.continueToAdyenBrandCode = function () {
                        // set payment method to adyen_hpp
                        var self = this;

                        if (this.validate() && additionalValidators.validate()) {
                            var data = {};
                            data.method = self.method;

                            var additionalData = {};
                            additionalData.brand_code = self.value;

                            if (self.hasIssuersAvailable()) {
                                additionalData.issuer_id = this.issuer();
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
                            } else if (self.isAch()) {
                                additionalData.bankAccountOwnerName = this.ownerName();
                                additionalData.bankAccountNumber = this.bankAccountNumber();
                                additionalData.bankLocationId = this.bankLocationId();
                            }

                            data.additional_data = additionalData;
                            this.placeRedirectOrder(data);
                        }

                        return false;
                    }


                    if (result.hasIssuersProperty()) {
                        if (!result.hasIssuersAvailable()) {
                            return false;
                        }

                        result.issuerIds = result.getIssuers();
                        result.issuer = ko.observable(null);
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
                    } else if (result.isAch()) {
                        result.ownerName = ko.observable(null);
                        result.bankAccountNumber = ko.observable(null);
                        result.bankLocationId = ko.observable(null);
                    }

                    accumulator.push(result);
                    return accumulator;
                }, []);

                return paymentList;
            },
            /**
             * Some payment methods we do not want to render as it requires extra implementation
             * or is already implemented in a separate payment method.
             * Using a match as we want to prevent to render all Boleto and most of the WeChat types
             * @param paymentMethod
             * @returns {boolean}
             */
            isPaymentMethodSupported: function (paymentMethod) {
                if (paymentMethod == 'wechatpayWeb') {
                    return true;
                }
                for (var i = 0; i < unsupportedPaymentMethods.length; i++) {
                    var match = paymentMethod.match(unsupportedPaymentMethods[i]);
                    if (match) {
                        return false;
                    }
                }
                return true;
            },
            getGenderTypes: function () {
                return _.map(window.checkoutConfig.payment.adyenHpp.genderTypes, function (value, key) {
                    return {
                        'key': key,
                        'value': value
                    }
                });
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
            /**
             * This method is a workaround to close the modal in the right way and reconstruct the ActionModal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function (popupModal) {
                popupModal.modal("closeModal");
                $('.ActionModal').remove();
                $('.modals-overlay').remove();
                $('body').removeClass('_has-modal');

                // reconstruct the ActionModal container again otherwise component can not find the ActionModal
                $('#ActionWrapper').append("<div id=\"ActionModal\">" +
                    "<div id=\"ActionContainer\"></div>" +
                    "</div>");
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
            isIconEnabled: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            /**
             * Based on the response we can start a action component or redirect
             * @param responseJSON
             */
            validateActionOrPlaceOrder: function (responseJSON, orderId) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.action) {
                    // render component
                    self.orderId = orderId;
                    self.renderActionComponent(response.action);
                } else {
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl
                    );
                }
            },
            /**
             * Rendering the 3DS2.0 components
             * To do the device fingerprint at the response of IdentifyShopper render the threeDS2DeviceFingerprint
             * component
             * To render the challenge for the customer at the response of ChallengeShopper render the
             * threeDS2Challenge component
             * Both of them is going to be rendered in a Magento dialog popup
             *
             * @param type
             * @param token
             */
            renderActionComponent: function (action) {
                var self = this;
                var actionNode = document.getElementById('ActionContainer');


                fullScreenLoader.stopLoader();

                self.popupModal = $('#ActionModal').modal({
                    // disable user to hide popup
                    clickableOverlay: false,
                    responsive: true,
                    innerScroll: false,
                    // empty buttons, we don't need that
                    buttons: [],
                    modalClass: 'ActionModal'
                });

                self.popupModal.modal("openModal");
                self.actionComponent = self.checkoutComponent.createFromAction(action).mount(actionNode);
            },
            handleOnAdditionalDetails: function (state, component) {
                var self = this;

                // call endpoint with state.data
                var request = state.data;
                request.orderId = self.orderId;

                // Using the same processor as 3DS2, refactor to generic name in a upcomming release will be breaking change for merchants.
                threeds2.processThreeDS2(request).done(function () {
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl
                    );
                }).fail(function (response) {
                    fullScreenLoader.stopLoader();
                    self.closeModal(self.popupModal);
                    errorProcessor.process(response, self.currentMessageContainer);
                    self.isPlaceOrderActionAllowed(true);
                    self.showErrorMessage(response);
                });
            },
            /**
             * Issue with the default currentMessageContainer needs to be resolved for now just throw manually the eror message
             * @param response
             */
            showErrorMessage: function (response) {
                if (!!response['responseJSON'].parameters) {
                    $("#messages-" + brandCode()).text((response['responseJSON'].message).replace('%1', response['responseJSON'].parameters[0])).slideDown();
                } else {
                    $("#messages-" + brandCode()).text(response['responseJSON'].message).slideDown();
                }

                setTimeout(function () {
                    $("#messages-" + brandCode()).slideUp();
                }, 10000);
            },
            validate: function (brandCode) {
                var form = '#payment_form_' + this.getCode() + '_' + brandCode;
                var validate = $(form).validation() && $(form).validation('isValid');

                if (!validate) {
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
            filterUndefinedItemsInArray: function (arr) {
                return arr.filter(function (item) {
                    return typeof item !== 'undefined';
                });
            },
            getOriginKey: function () {
                return window.checkoutConfig.payment.adyen.originKey;
            },
            getCheckoutEnvironment: function () {
                return window.checkoutConfig.payment.adyen.checkoutEnvironment;
            }
        });
    }
);
