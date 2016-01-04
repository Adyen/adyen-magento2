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
        'underscore',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Adyen_Payment/js/action/place-order',
        'mage/translate',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Adyen_Payment/js/view/payment/adyen-encrypt'
    ],
    function (_, $, Component, placeOrderAction, $t, additionalValidators) {
        'use strict';
        $.validator.addMethod(
            'validate-custom-required', function (value) {
                return (value === 'test'); // Validation logic here
            }, $.mage.__('Enter This is a required field custom.')
        );
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
                        'generationtime'
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
                return 'adyen_cc';
            },
            getData: function() {
                return {
                    'method': this.item.method,
                    additional_data: {
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_number': this.creditCardNumber(),
                        'cc_owner' : this.creditCardOwner(),
                        'cc_cid': this.creditCardVerificationNumber(),
                        'cc_ss_start_month': this.creditCardSsStartMonth(),
                        'cc_ss_start_year': this.creditCardSsStartYear(),
                        'encrypted_data': this.encryptedData(),
                        'generationtime': this.generationtime()
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

                //var cse_form = $("adyen-cc-form");
                var cse_form = document.getElementById('adyen-cc-form');
                var cse_key = this.getCSEKey();
                var options = {};

                var cseInstance = adyen.encrypt.createEncryption(cse_key, options);
                var generationtime = self.getGenerationTime();

                var cardData = {
                    number : self.creditCardNumber(),
                    cvc : self.creditCardVerificationNumber(),
                    holderName : self.creditCardOwner(),
                    expiryMonth : self.creditCardExpMonth(),
                    expiryYear : self.creditCardExpYear(),
                    generationtime : generationtime
                };

                var data = cseInstance.encrypt(cardData);
                self.encryptedData(data);

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), this.redirectAfterPlaceOrder);

                    $.when(placeOrder).fail(function(response) {
                        self.isPlaceOrderActionAllowed(true);
                    });
                    return true;
                }
                return false;
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
            validate: function () {
                var form = 'form[data-role=adyen-cc-form]';

                var validate =  $(form).validation() && $(form).validation('isValid');
                // add extra validation because jqeury validation will not work on non name attributes
                var ccNumber = Boolean($(form + ' #adyen_cc_cc_number').valid());
                var owner = Boolean($(form + ' #adyen_cc_cc_owner').valid());
                var expiration = Boolean($(form + ' #adyen_cc_expiration').valid());
                var expiration_yr = Boolean($(form + ' #adyen_cc_expiration_yr').valid());
                var $cid = Boolean($(form + ' #adyen_cc_cc_cid').valid());

                if(!validate || !ccNumber || !owner || !expiration || !expiration_yr || !$cid) {
                    return false;
                }

                return true;
            }
        });
    }
);


