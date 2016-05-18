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
        'adyen/encrypt',
    ],
    function (_, $, Component, placeOrderAction, $t, additionalValidators, adyenEncrypt) {

        'use strict';
        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/cc-form',
                creditCardOwner: '',
                encryptedData: '',
                setStoreCc: true
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
                        'generationtime',
                        'setStoreCc'
                    ]);
                return this;
            },
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
                        'encrypted_data': this.encryptedData(),
                        'generationtime': this.generationtime(),
                        'store_cc': this.setStoreCc()
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
                var options = {};

                var cseInstance = adyenEncrypt.createEncryption(cse_key, options);
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
            canCreateBillingAgreement: function() {
                return window.checkoutConfig.payment.adyenCc.canCreateBillingAgreement;
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
                var cid = Boolean($(form + ' #adyen_cc_cc_cid').valid());

                if(!validate || !ccNumber || !owner || !expiration || !expiration_yr || !cid) {
                    return false;
                }

                return true;
            },
            showLogo: function() {
                return window.checkoutConfig.payment.adyen.showLogo;
            }
        });
    }
);


