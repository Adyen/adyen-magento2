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
        var updatedExpiryDate = false;
        var recurringDetailReference = ko.observable(null);
        var variant = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var encryptedData = ko.observable(null);
        var numberOfInstallments = ko.observable(null);
        var messageComponents;
        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/oneclick-form',
                recurringDetailReference: '',
                encryptedData: '',
                variant: '',
                numberOfInstallments: ''
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'recurringDetailReference',
                        'creditCardType',
                        'creditCardVerificationNumber',
                        'encryptedData',
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
            placeOrderHandler: null,
            validateHandler: null,
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getCode: function () {
                return 'adyen_oneclick';
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
                    if (value.number_of_installments > 0) {
                        for (i = 1; i <= value.number_of_installments; i++) {
                            installments.push({
                                key: i,
                                value: i
                            });
                        }
                    }

                    var messageContainer = self.messageComponents['messages-' + value.reference_id];

                    return {
                        'expiry': ko.observable(false),
                        'label': value.agreement_label,
                        'value': value.reference_id,
                        'agreement_data': value.agreement_data,
                        'logo': value.logo,
                        'installment': '',
                        'number_of_installments': value.number_of_installments,
                        getInstallments: ko.observableArray(installments),
                        'method': self.item.method,
                        getCode: function () {
                            return self.item.method;
                        },
                        creditCardVerificationNumber: '',
                        creditCardExpMonth: ko.observable(creditCardExpMonth),
                        creditCardExpYear: ko.observable(creditCardExpYear),
                        getGenerationTime: function () {
                            return window.checkoutConfig.payment.adyenCc.generationTime;
                        },
                        hasVerification: function () {
                            return window.checkoutConfig.payment.adyenOneclick.hasCustomerInteraction;
                        },
                        /**
                         * @override
                         */
                        placeOrder: function (data, event) {
                            var self = this;

                            if (event) {
                                event.preventDefault();
                            }

                            var data = {
                                "method": self.method,
                                "additional_data": {
                                    variant: self.agreement_data.variant,
                                    recurring_detail_reference: self.value
                                }
                            }

                            // only use CSE and installments for cards
                            if (self.agreement_data.card) {

                                var generationtime = self.getGenerationTime();

                                var cardData = {
                                    cvc: self.creditCardVerificationNumber,
                                    expiryMonth: self.creditCardExpMonth(),
                                    expiryYear: self.creditCardExpYear(),
                                    generationtime: generationtime
                                };

                                if (updatedExpiryDate || self.hasVerification()) {

                                    var options = {enableValidations: false};
                                    var cseInstance = adyen.createEncryption(options);
                                    var encryptedDataResult = cseInstance.encrypt(cardData);
                                    encryptedData(encryptedDataResult)
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
                                // debugger;;
                                return true;
                            }
                            return false;
                        },
                        getData: function () {
                            return {
                                "method": self.item.method,
                                "additional_data": {
                                    variant: variant(),
                                    recurring_detail_reference: recurringDetailReference(),
                                    number_of_installments: numberOfInstallments(),
                                    encrypted_data: encryptedData()
                                }
                            };
                        },
                        isPlaceOrderActionAllowed: function () {
                            return self.isPlaceOrderActionAllowed(); // needed for placeOrder method
                        },
                        afterPlaceOrder: function () {
                            return self.afterPlaceOrder(); // needed for placeOrder method
                        },
                        getPlaceOrderDeferredObject: function () {
                            // debugger;;
                            return $.when(
                                placeOrderAction(this.getData(), this.getMessageContainer())
                            );
                        },
                        validate: function () {

                            var code = self.item.method;
                            var value = this.value;
                            var codeValue = code + '_' + value;

                            var form = 'form[data-role=' + codeValue + ']';

                            var validate = $(form).validation() && $(form).validation('isValid');

                            // if oneclick or recurring is a card do validation on expiration date
                            if (this.agreement_data.card) {
                                // add extra validation because jquery validation will not work on non name attributes
                                var expiration = Boolean($(form + ' #' + codeValue + '_expiration').valid());
                                var expiration_yr = Boolean($(form + ' #' + codeValue + '_expiration_yr').valid());

                                // only check if recurring type is set to oneclick
                                var cid = true;
                                if (this.hasVerification()) {
                                    var cid = Boolean($(form + ' #' + codeValue + '_cc_cid').valid());
                                }
                            } else {
                                var expiration = true;
                                var expiration_yr = true;
                                var cid = true;
                            }

                            if (!validate || !expiration || !expiration_yr || !cid) {
                                return false;
                            }

                            return true;
                        },
                        selectExpiry: function () {
                            updatedExpiryDate = true;
                            var self = this;
                            self.expiry(true);
                            return true;
                        },
                        getRegion: function (name) {
                            self.getRegion(name);
                        },
                        getMessageName: function () {
                            return 'messages-' + value.reference_id;
                        },
                        getMessageContainer: function () {
                            return messageContainer;
                        },
                        /**
                         * @return {String}
                         */
                        getBillingAddressFormName: function () {
                            return 'billing-address-form-' + self.item.method;
                        }
                    }
                });
                return paymentList;
            },
            selectBillingAgreement: function () {
                var self = this;
                self.expiry(false);
                updatedExpiryDate = false;

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
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            }
        });
    }
);


