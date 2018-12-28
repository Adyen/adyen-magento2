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
        'Magento_Checkout/js/action/place-order',
        'mage/url'
    ],
    function (ko, _, $, Component, selectPaymentMethodAction, additionalValidators, quote, checkoutData, redirectOnSuccessAction, layout, Messages, placeOrderAction, url) {

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
                        'creditCardVerificationNumber',
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
                        'placeOrderAllowed': ko.observable(false),


                        isButtonActive: function() {
                            return self.isActive() && this.getCode() == self.isChecked() && self.isBillingAgreementChecked()  && this.placeOrderAllowed();
                        },
                        /**
                         * @override
                         */
                        placeOrder: function (data, event) {
                            var self = this;

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

                            // in different context so need custom place order logic
                            if (this.validate() && additionalValidators.validate()) {
                                self.isPlaceOrderActionAllowed(false);

                                this.getPlaceOrderDeferredObject()
                                    .fail(
                                        function () {
                                            self.isPlaceOrderActionAllowed(true);
                                        }
                                    ).done(
                                    function () {
                                        self.afterPlaceOrder();
                                        // use custom redirect Link for supporting 3D secure
                                        window.location.replace(url.build(window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl));
                                    }
                                );
                                return true;
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

                            var oneClickCardNode = document.getElementById('cvcContainer-' + self.value);

                            var checkout = new AdyenCheckout({
                                locale: self.getLocale()
                            });

                            // this should be fixed in new version of checkout card component
                            var hideCVC = false;
                            if (self.agreement_data.variant == "bcmc") {
                                hideCVC = true;
                                self.placeOrderAllowed(true);
                            } else if(self.agreement_data.variant == "maestro") {
                                // for maestro cvc is optional
                                self.placeOrderAllowed(true);
                            }

                            var oneClickCard = checkout
                                .create('card', {
                                    originKey: self.getOriginKey(),
                                    type: self.agreement_data.variant,
                                    oneClick: true,
                                    hideCVC: hideCVC,

                                    // Specific for oneClick cards
                                    details: [
                                        {
                                            "key": "cardDetails.cvc",
                                            "type": "cvc"
                                        }
                                    ],
                                    storedDetails: {
                                        "card": {
                                            "expiryMonth": self.agreement_data.card.expiryMonth,
                                            "expiryYear": self.agreement_data.card.expiryYear,
                                            "holderName": self.agreement_data.card.holderName,
                                            "number": self.agreement_data.card.number
                                        }
                                    },

                                    onChange: function (state) {
                                        if (state.isValid) {
                                            self.encryptedCreditCardVerificationNumber = state.data.encryptedSecurityCode;
                                        } else {
                                            self.encryptedCreditCardVerificationNumber = '';
                                        }
                                    },
                                    onValid: function (state) {
                                        if (state.isValid) {
                                            self.placeOrderAllowed(true);
                                            isValid(true);
                                        } else {
                                            isValid(false);
                                        }
                                        return;
                                    },
                                    onError: function(data) {
                                        self.placeOrderAllowed(false);
                                        isValid(false);
                                        return;
                                    }
                                })
                                .mount(oneClickCardNode);


                            window.adyencheckout = oneClickCard;
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
                        getLocale: function () {
                            return window.checkoutConfig.payment.adyenOneclick.locale;
                        },
                        getOriginKey: function () {
                            return window.checkoutConfig.payment.adyenOneclick.originKey;
                        },
                        getLoadingContext: function () {
                            return window.checkoutConfig.payment.adyenOneclick.checkoutUrl;
                        },
                        hasVerification: function () {
                            return window.checkoutConfig.payment.adyenOneclick.hasCustomerInteraction;
                        },
                        getMessageName: function () {
                            return 'messages-' + value.reference_id;
                        },
                        getMessageContainer: function () {
                            return messageContainer;
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
            }
        });
    }
);
