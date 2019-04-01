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
        'underscore',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/redirect-on-success',
        'uiLayout',
        'Magento_Ui/js/model/messages',
        'mage/url',
        'Adyen_Payment/js/threeds2-js-utils',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/action/place-order'
    ],
    function (ko, _, $, Component, selectPaymentMethodAction, additionalValidators, quote, checkoutData, redirectOnSuccessAction, layout, Messages, url, threeDS2Utils, fullScreenLoader, setPaymentMethodAction, urlBuilder, storage, placeOrderAction) {

        'use strict';

        var messageComponents;

        var recurringDetailReference = ko.observable(null);
        var variant = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var numberOfInstallments = ko.observable(null);
        var isValid = ko.observable(false);

        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/oneclick-form',
                recurringDetailReference: '',
                variant: '',
                numberOfInstallments: ''
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'recurringDetailReference',
                        'creditCardType',
                        'encryptedCreditCardVerificationNumber',
                        'variant',
                        'numberOfInstallments'
                    ]);
                return this;
            },
            initialize: function () {
                var self = this;
                this._super();

                // create component needs to be in initialize method
                var messageComponents = {};
                _.map(window.checkoutConfig.payment.adyenOneclick.billingAgreements, function (value) {

                    var messageContainer = new Messages();
                    var name = 'messages-' + value.reference_id;
                    var messagesComponent = {
                        parent: self.name,
                        name: 'messages-' + value.reference_id,
                        // name: self.name + '.messages',
                        displayArea: 'messages-' + value.reference_id,
                        component: 'Magento_Ui/js/view/messages',
                        config: {
                            messageContainer: messageContainer
                        }
                    };
                    layout([messagesComponent]);

                    messageComponents[name] = messageContainer;
                });
                this.messageComponents = messageComponents;
            },
            /**
             * List all Adyen billing agreements
             * Set up installments
             *
             * @returns {Array}
             */
            getAdyenBillingAgreements: function () {
                var self = this;

                // shareable adyen checkout component
                var checkout = new AdyenCheckout({
                    locale: self.getLocale(),
                    originKey: self.getOriginKey(),
                    loadingContext: self.getLoadingContext()
                });

                // convert to list so you can iterate
                var paymentList = _.map(window.checkoutConfig.payment.adyenOneclick.billingAgreements, function (value) {

                    var creditCardExpMonth, creditCardExpYear = false;

                    if (value.agreement_data.card) {
                        creditCardExpMonth = value.agreement_data.card.expiryMonth;
                        creditCardExpYear = value.agreement_data.card.expiryYear;
                    }

                    // pre-define installments if they are set
                    var i, installments = [];
                    var grandTotal = quote.totals().grand_total;
                    var dividedString = "";
                    var dividedAmount = 0;

                    if (value.number_of_installments) {
                        for (i = 0; i < value.number_of_installments.length; i++) {
                            dividedAmount = (grandTotal / value.number_of_installments[i]).toFixed(quote.getPriceFormat().precision);
                            dividedString = value.number_of_installments[i] + " x " + dividedAmount + " " + quote.totals().quote_currency_code;

                            installments.push({
                                key: [dividedString],
                                value: value.number_of_installments[i]
                            });
                        }
                    }

                    var messageContainer = self.messageComponents['messages-' + value.reference_id];

                    // for recurring enable the placeOrder button at all times
                    var placeOrderAllowed = true;
                    if (self.hasVerification()) {
                        placeOrderAllowed = false;
                    } else {
                        // for recurring cards there is no validation needed
                        isValid(true);
                    }

                    return {
                        'label': value.agreement_label,
                        'value': value.reference_id,
                        'agreement_data': value.agreement_data,
                        'logo': value.logo,
                        'installment': '',
                        'number_of_installments': value.number_of_installments,
                        'method': self.item.method,
                        'encryptedCreditCardVerificationNumber': '',
                        'creditCardExpMonth': ko.observable(creditCardExpMonth),
                        'creditCardExpYear': ko.observable(creditCardExpYear),
                        'getInstallments': ko.observableArray(installments),
                        'placeOrderAllowed': ko.observable(placeOrderAllowed),


                        isButtonActive: function () {
                            return self.isActive() && this.getCode() == self.isChecked() && self.isBillingAgreementChecked() && this.placeOrderAllowed();
                        },
                        /**
                         * Custom place order function
                         *
                         * @override
                         *
                         * @param data
                         * @param event
                         * @returns {boolean}
                         */
                        placeOrder: function (data, event) {
                            var self = this;
                            console.log("placeorder");

                            if (event) {
                                event.preventDefault();
                            }
                            // only use installments for cards
                                if (self.agreement_data.card) {
                                    if (self.hasVerification()) {
                                        var options = {enableValidations: false};
                                    }

                                    // set payment method to adyen_hpp
                                    // TODO can observer in front-end this not needed
                                    numberOfInstallments(self.installment);
                                }

                            if (this.validate() && additionalValidators.validate()) {
                                fullScreenLoader.startLoader();
                                self.isPlaceOrderActionAllowed(false);

                                //update payment method information if additional data was changed
                                selectPaymentMethodAction(this.getCcData());
                                setPaymentMethodAction(this.messageContainer).done(
                                    function (responseJSON) {
                                        fullScreenLoader.stopLoader();
                                        self.isPlaceOrderActionAllowed(true);
                                        self.validateThreeDS2OrPlaceOrder(responseJSON);
                                    });
                                return false;
                            }

                            return false;
                        },

                        /**
                         * Renders the secure CVC field,
                         * creates the card component,
                         * sets up the callbacks for card components
                         */
                        renderSecureCVC: function () {
                            var self = this;

                            if (!self.getOriginKey()) {
                                return;
                            }
                            var oneClickCardNode = document.getElementById('cvcContainer-' + self.value);

                            // this should be fixed in new version of checkout card component
                            var hideCVC = false;
                            if (this.hasVerification()) {
                                if (self.agreement_data.variant == "maestro") {
                                    // for maestro cvc is optional
                                    self.placeOrderAllowed(true);
                                }
                            } else {
                                hideCVC = true;
                            }

                            var oneClickCard = checkout
                                .create('card', {
                                    type: self.agreement_data.variant,
                                    hideCVC: hideCVC,
                                    details: self.getOneclickDetails(),
                                    storedDetails: {
                                        "card": {
                                            "expiryMonth": self.agreement_data.card.expiryMonth,
                                            "expiryYear": self.agreement_data.card.expiryYear,
                                            "holderName": self.agreement_data.card.holderName,
                                            "number": self.agreement_data.card.number
                                        }
                                    },
                                    onChange: function (state, component) {
                                        if (state.isValid) {
                                            self.placeOrderAllowed(true);
                                            isValid(true);

                                            if (typeof state.data !== 'undefined' &&
                                                typeof state.data.encryptedSecurityCode !== 'undefined'
                                            ) {
                                                self.encryptedCreditCardVerificationNumber = state.data.encryptedSecurityCode;
                                            }
                                        } else {
                                            self.encryptedCreditCardVerificationNumber = '';

                                            if (self.agreement_data.variant != "maestro") {
                                                self.placeOrderAllowed(false);
                                                isValid(false);
                                            }
                                        }

                                        // When we move to the component v2.2 it should be removed
                                        if (self.agreement_data.variant == "maestro" &&
                                            component.state.errors.encryptedSecurityCode
                                        ) {
                                            self.placeOrderAllowed(false);
                                            isValid(false);
                                        }
                                    }
                                })
                                .mount(oneClickCardNode);


                            window.adyencheckout = oneClickCard;
                        },
                        /**
                         * Builds the payment details part of the payment information reqeust
                         *
                         * @returns {{method: *, additional_data: {card_brand: *, cc_type: *, number: *, cvc: *, expiryMonth: *, expiryYear: *, holderName: *, store_cc: (boolean|*), number_of_installments: *, java_enabled: boolean, screen_color_depth: number, screen_width, screen_height, timezone_offset: *}}}
                         */
                        getCcData: function () {
                            var self = this;
                            var browserInfo = threeDS2Utils.getBrowserInfo();
                            var data = {
                                'method': self.method,
                                additional_data: {
                                    'variant': variant(),
                                    'recurring_detail_reference': recurringDetailReference(),
                                    'number_of_installments': numberOfInstallments(),
                                    'cvc': self.encryptedCreditCardVerificationNumber,
                                    'expiryMonth': self.creditCardExpMonth(),
                                    'expiryYear': self.creditCardExpYear(),

                                    'java_enabled': browserInfo.javaEnabled,
                                    'screen_color_depth': browserInfo.colorDepth,
                                    'screen_width': browserInfo.screenWidth,
                                    'screen_height': browserInfo.screenHeight,
                                    'timezone_offset': browserInfo.timeZoneOffset
                                }
                            };
                            return data;
                        },
                        /**
                         * Based on the response we can start a 3DS2 validation or place the order
                         * @param responseJSON
                         */
                        validateThreeDS2OrPlaceOrder: function(responseJSON) {
                            var self = this;
                            var response = JSON.parse(responseJSON);

                            if (!!response.threeDS2) {
                                // render component
                                self.renderThreeDS2Component(response.type, response.token);
                            } else {
                                this.getPlaceOrderDeferredObject()
                                    .fail(
                                        function () {
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
                        /**
                         * The results that the 3DS2 components returns in the onComplete callback needs to be sent to the
                         * backend to the /adyen/threeDS2Process endpoint and based on the response render a new threeDS2
                         * component or place the order (validateThreeDS2OrPlaceOrder)
                         * @param response
                         */
                        processThreeDS2: function(data) {
                            var self = this;

                            fullScreenLoader.startLoader();

                            var payload = {
                                "payload": JSON.stringify(data)
                            };

                            var serviceUrl = urlBuilder.createUrl('/adyen/threeDS2Process', {});

                            storage.post(
                                serviceUrl,
                                JSON.stringify(payload),
                                true
                            ).done(function(responseJSON) {
                                fullScreenLoader.stopLoader();
                                self.validateThreeDS2OrPlaceOrder(responseJSON)
                            });
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
                        renderThreeDS2Component: function(type, token) {
                            var self = this;

                            var threeDS2Node = document.getElementById('threeDS2ContainerOneClick');

                            if (type == "IdentifyShopper") {
                                fullScreenLoader.startLoader();
                                self.threeDS2Component = checkout.create('threeDS2DeviceFingerprint', {
                                        fingerprintToken: token,
                                        onComplete: function(result) {
                                            fullScreenLoader.stopLoader();
                                            self.processThreeDS2(result.data);
                                        }
                                    });
                            } else if (type == "ChallengeShopper") {
                                $('#threeDS2ModalOneClick').modal({
                                    // disable user to hide popup
                                    clickableOverlay: false,
                                    // empty buttons, we don't need that
                                    buttons: []
                                });

                                $('#threeDS2ModalOneClick').modal("openModal");

                                self.threeDS2Component = checkout
                                    .create('threeDS2Challenge', {
                                        challengeToken: token,
                                        onComplete: function(result) {
                                            self.processThreeDS2(result.data);
                                            $('#threeDS2ModalOneClick').modal("closeModal");
                                        }
                                    });
                            }

                            self.threeDS2Component.mount(threeDS2Node);
                        },
                        /**
                         * We use the billingAgreements to save the oneClick stored payments but we don't store the
                         * details object that we get from the paymentMethods call. This function is a fix for BCMC.
                         * When we render the stored payments dynamically from the paymentMethods call response it
                         * should be removed
                         * @returns {*}
                         */
                        getOneclickDetails: function() {
                            var self = this;

                            if (self.agreement_data.variant === 'bcmc') {
                                return [];
                            } else {
                                return [
                                    {
                                        "key": "cardDetails.cvc",
                                        "type": "cvc"
                                    }
                                ];
                            }
                        },
                        /**
                         * Builds the payment details part of the payment information reqeust
                         *
                         * @returns {{method: *, additional_data: {variant: *, recurring_detail_reference: *, number_of_installments: *, cvc: (string|*), expiryMonth: *, expiryYear: *}}}
                         */
                        getData: function () {
                            var self = this;

                            return {
                                "method": self.method,
                                additional_data: {
                                    variant: variant(),
                                    recurring_detail_reference: recurringDetailReference(),
                                    number_of_installments: numberOfInstallments(),
                                    cvc: self.encryptedCreditCardVerificationNumber,
                                    expiryMonth: self.creditCardExpMonth(),
                                    expiryYear: self.creditCardExpYear()
                                }
                            };
                        },
                        validate: function () {

                            var code = self.item.method;
                            var value = this.value;
                            var codeValue = code + '_' + value;

                            var form = 'form[data-role=' + codeValue + ']';

                            var validate = $(form).validation() && $(form).validation('isValid');

                            // bcmc does not have any cvc
                            if (!validate || (isValid() == false && variant() != "bcmc" && variant() != "maestro")) {
                                return false;
                            }

                            return true;
                        },
                        getCode: function () {
                            return self.item.method;
                        },
                        hasVerification: function () {
                            return self.hasVerification()
                        },
                        getMessageName: function () {
                            return 'messages-' + value.reference_id;
                        },
                        getMessageContainer: function () {
                            return messageContainer;
                        },
                        getOriginKey:function () {
                            return self.getOriginKey();
                        },
                        isPlaceOrderActionAllowed: function () {
                            return self.isPlaceOrderActionAllowed(); // needed for placeOrder method
                        },
                        afterPlaceOrder: function () {
                            return self.afterPlaceOrder(); // needed for placeOrder method
                        },
                        getPlaceOrderDeferredObject: function () {
                            return $.when(
                                placeOrderAction(this.getData(), this.getMessageContainer())
                            );
                        },
                    }
                });

                return paymentList;
            },
            /**
             * Select a billing agreement (stored one click payment method) from the list
             *
             * @returns {boolean}
             */
            selectBillingAgreement: function () {
                var self = this;

                // set payment method data
                var data = {
                    "method": self.method,
                    "po_number": null,
                    "additional_data": {
                        recurring_detail_reference: self.value
                    }
                };

                // set the brandCode
                recurringDetailReference(self.value);
                variant(self.agreement_data.variant);

                // set payment method
                paymentMethod(self.method);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },
            isBillingAgreementChecked: ko.computed(function () {

                if (!quote.paymentMethod()) {
                    return null;
                }

                if (quote.paymentMethod().method == paymentMethod()) {
                    return recurringDetailReference();
                }

                return null;
            }),
            placeOrderHandler: null,
            validateHandler: null,
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            getCode: function () {
                return window.checkoutConfig.payment.adyenOneclick.methodCode;
            },
            isActive: function () {
                return true;
            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            context: function () {
                return this;
            },
            canCreateBillingAgreement: function () {
                return window.checkoutConfig.payment.adyenCc.canCreateBillingAgreement;
            },
            isShowLegend: function () {
                return true;
            },
            hasVerification: function () {
                return window.checkoutConfig.payment.adyenOneclick.hasCustomerInteraction;
            },
            getLocale: function () {
                return window.checkoutConfig.payment.adyenOneclick.locale;
            },
            getOriginKey: function () {
                return window.checkoutConfig.payment.adyenOneclick.originKey;
            },
            getLoadingContext: function () {
                return window.checkoutConfig.payment.adyenOneclick.checkoutUrl;
            }
        });
    }
);
