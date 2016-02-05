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
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'underscore',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Adyen_Payment/js/action/place-order',
        'mage/translate',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Adyen_Payment/js/view/payment/adyen-encrypt'
    ],
    function (ko, _, $, Component, placeOrderAction, $t, additionalValidators, selectPaymentMethodAction, quote, checkoutData) {
        'use strict';
        var recurringDetailReference = ko.observable(null);
        var paymentMethod = ko.observable(null);
        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/oneclick-form',
                recurringDetailReference: '',
                encryptedData: ''
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'recurringDetailReference',
                        'creditCardType',
                        'creditCardExpYear',
                        'creditCardExpMonth',
                        'creditCardVerificationNumber',
                        'encryptedData'
                    ]);
                return this;
            },
            placeOrderHandler: null,
            validateHandler: null,
            setPlaceOrderHandler: function(handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function(handler) {
                this.validateHandler = handler;
            },
            getCode: function() {
                return 'adyen_oneclick';
            },
            getData: function() {
                return {
                    'method': this.item.method,
                    additional_data: {
                        'cc_type': this.creditCardType(),
                        'cc_cid': this.creditCardVerificationNumber(),
                        'encrypted_data': this.encryptedData()
                    }
                };
            },
            isActive: function() {
                return true;
            },
            /**
             * @override
             */
            placeOrder: function(data, event) {
                var self = this,
                    placeOrder;

                if (event) {
                    event.preventDefault();
                }

                var cse_key = this.getCSEKey();
                var options = { enableValidations: false};

                var cseInstance = adyen.encrypt.createEncryption(cse_key, options);
                var generationtime = self.getGenerationTime();

                var cardData = {
                    cvc : self.creditCardVerificationNumber,
                    expiryMonth : self.creditCardExpMonth,
                    expiryYear : self.creditCardExpYear,
                    generationtime : generationtime
                };

                var encryptedData = cseInstance.encrypt(cardData);

                // set payment method to adyen_hpp
                var  data = {
                    "method": self.method,
                    "po_number": null,
                    "additional_data": {
                        encrypted_data: encryptedData,
                        recurring_detail_reference: self.value
                    }
                };


                if (this.validate() && additionalValidators.validate()) {
                    //this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(data, this.redirectAfterPlaceOrder);

                    $.when(placeOrder).fail(function(response) {
                        //self.isPlaceOrderActionAllowed(true);
                    });
                    return true;
                }
                return false;
            },
            getControllerName: function() {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            context: function() {
                return this;
            },
            isCseEnabled: function() {
                return window.checkoutConfig.payment.adyenCc.cseEnabled;
            },
            canCreateBillingAgreement: function() {
                return window.checkoutConfig.payment.adyenCc.canCreateBillingAgreement;
            },
            isShowLegend: function() {
                return true;
            },
            getAdyenBillingAgreements: function() {
                var self = this;
                // convert to list so you can iterate
                var paymentList = _.map(window.checkoutConfig.payment.adyenOneclick.billingAgreements, function(value) {


                        return {
                            'expiry': ko.observable(false),
                            'test': true,
                            'test2': true,
                            'label': value.agreement_label,
                            'value': value.reference_id,
                            'agreement_data': value.agreement_data,
                            'method': self.item.method,
                            getCode: function() {
                                return self.item.method;
                            },
                            creditCardVerificationNumber: '',
                            creditCardExpMonth: value.agreement_data.card.expiryMonth,
                            creditCardExpYear: value.agreement_data.card.expiryYear,
                            getCSEKey: function() {
                                return window.checkoutConfig.payment.adyenCc.cseKey;
                            },
                            getGenerationTime: function() {
                                return window.checkoutConfig.payment.adyenCc.generationTime;
                            },
                            validate: function () {

                                var code = self.item.method;
                                var value = this.value;
                                var codeValue = code + '_' + value;

                                var form = 'form[data-role=' + codeValue + ']';
                                var formObject = $(form);

                                var validate =  $(form).validation() && $(form).validation('isValid');

                                // add extra validation because jqeury validation will not work on non name attributes
                                var expiration = Boolean($(form + ' #' + codeValue + '_expiration').valid());
                                var expiration_yr = Boolean($(form + ' #' + codeValue + '_expiration_yr').valid());


                                // only check if recurring type is set to oneclick
                                var cid = true;
                                if(self.hasVerification()) {
                                    var cid = Boolean($(form + ' #' + codeValue + '_cc_cid').valid());
                                }

                                if(!validate || !expiration || !expiration_yr || !cid) {
                                    return false;
                                }

                                return true;
                            },
                            selectExpiry: function() {
                                var self = this;
                                self.expiry(true);
                                return true;
                            }
                        }
                    }
                );
                return paymentList;
            },
            selectBillingAgreement: function() {
                var self = this;

                // set payment method to adyen_hpp
                var  data = {
                    "method": self.method,
                    "po_number": null,
                    "additional_data": {
                        recurring_detail_reference: self.value
                    }
                };

                // set the brandCode
                recurringDetailReference(self.value);

                // set payment method
                paymentMethod(self.method);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },
            isBillingAgreementChecked: ko.computed(function () {

                if(!quote.paymentMethod()) {
                    return null;
                }

                if(quote.paymentMethod().method == paymentMethod()) {
                    return recurringDetailReference();
                }
                return null;
            }),
            getRecurringContractType: function() {
                return window.checkoutConfig.payment.adyenOneClick.recurringContractType;
            },
            hasVerification: function() {
                return window.checkoutConfig.payment.adyenOneclick.hasCustomerInteraction;
            },
            getPlaceOrderUrl: function() {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            }
        });
    }
);


