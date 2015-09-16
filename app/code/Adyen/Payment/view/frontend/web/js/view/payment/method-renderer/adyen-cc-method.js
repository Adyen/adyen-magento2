/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'underscore',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/action/place-order',
        'mage/translate',
        'Adyen_Payment/js/view/payment/adyen-encrypt'
    ],
    function (_, $, Component, setPaymentInformationAction, placeOrderAction, $t) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/cc-form',
                creditCardOwner: '',
                encryptedData: ''
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'creditCardType',
                        'creditCardExpYear',
                        'creditCardExpMonth',
                        'creditCardNumber',
                        'creditCardVerificationNumber',
                        'creditCardSsStartMonth',
                        'creditCardSsStartYear',
                        'selectedCardType',
                        'creditCardOwner',
                        'encryptedData',
                    ]);
                return this;
            },
            initialize: function() {
                var self = this;
                this._super();



                // when creditCarNumber change call encrypt function
                this.creditCardNumber.subscribe(function(value) {
                    self.calculateCseKey();
                });
                this.creditCardOwner.subscribe(function(value) {
                    self.calculateCseKey();
                });
                //this.creditCardExpMonth.subscribe(function(value) {
                //    self.calculateCseKey();
                //});
                //this.creditCardExpYear.subscribe(function(value) {
                //    self.calculateCseKey();
                //});
                this.creditCardVerificationNumber.subscribe(function(value) {
                    self.calculateCseKey();
                });

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
                return 'adyen_cc';
            },
            getData: function() {
                return {
                    'method': this.item.method,
                    'cc_type': this.creditCardType(),
                    'cc_exp_year': this.creditCardExpYear(),
                    'cc_exp_month': this.creditCardExpMonth(),
                    'cc_number': this.creditCardNumber(),
                    'cc_owner' : this.creditCardOwner(),
                    additional_data: {
                        'cc_cid': this.creditCardVerificationNumber(),
                        'cc_ss_start_month': this.creditCardSsStartMonth(),
                        'cc_ss_start_year': this.creditCardSsStartYear(),
                        'encrypted_data': this.encryptedData()
                    }
                };
            },
            isActive: function() {
                return true;
            },
            placeOrder: function() {
                var self = this;

                //var cse_form = $("adyen-cc-form");
                var cse_form = document.getElementById('adyen-cc-form');
                var cse_key = this.getCSEKey();
                var cse_options = {
                    name:  'payment[encrypted_data]',
                    enableValidations: true,
                    //submitButtonAlwaysEnabled: true
                };

                var cseInstance = adyen.encrypt.createEncryptedForm(cse_form, cse_key, cse_options);

                // TODO needs to be done through php
                var generation = new Date().toISOString();

                var cardData = {
                    number : self.creditCardNumber,
                    cvc : self.creditCardVerificationNumber,
                    holderName : self.creditCardOwner,
                    expiryMonth : self.creditCardExpMonth,
                    expiryYear : self.creditCardExpYear,
                    generationtime : generation
                };

                var data = cseInstance.encrypt(cardData);

                self.encryptedData(data);


                var placeOrder = placeOrderAction(this.getData(), this.redirectAfterPlaceOrder);

                $.when(placeOrder).fail(function(){
                    self.isPlaceOrderActionAllowed(true);
                });
                //return true;
                //
                //if (this.validateHandler()) {
                //    this.isPlaceOrderActionAllowed(false);
                //    $.when(setPaymentInformationAction()).done(function() {
                //        self.placeOrderHandler();
                //    }).fail(function() {
                //        self.isPlaceOrderActionAllowed(true);
                //    });
                //}
            },
            getTitle: function() {
                return 'Adyen cc';
            },
            getControllerName: function() {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            getPlaceOrderUrl: function() {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            context: function() {
                return this;
            },
            isCseEnabled: function() {
                return window.checkoutConfig.payment.adyenCc.cseEnabled;
            },
            getCSEKey: function() {
                return window.checkoutConfig.payment.adyenCc.cseKey;
            },
            getGenerationTime: function() {
                return window.checkoutConfig.payment.adyenCc.generationTime;
            },
            isShowLegend: function() {
                return true;
            },
            calculateCseKey: function() {

                //
                ////var cse_form = $("adyen-cc-form");
                //var cse_form = document.getElementById('adyen-cc-form');
                //var cse_key = this.getCSEKey();
                //var cse_options = {
                //    name:  'payment[encrypted_data]',
                //    enableValidations: true, // disable because month needs to be 01 isntead of 1
                //    //submitButtonAlwaysEnabled: true
                //};
                //
                //var result = adyen.encrypt.createEncryptedForm(cse_form, cse_key, cse_options);



            }
        });
    }
);


