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
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Checkout/js/model/quote',
        'ko',
        'Adyen_Payment/js/model/installments',
        'adyen/encrypt'
    ],
    function (_, $, Component, placeOrderAction, $t, additionalValidators, customer, creditCardData, quote, ko, installments, adyenEncrypt) {

        'use strict';
        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/cc-form',
                creditCardOwner: '',
                encryptedData: '',
                setStoreCc: true,
                installment: ''
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
                        'setStoreCc',
                        'installment'
                    ]);
                return this;
            },
            getInstallments: installments.getInstallments(),
            initialize: function() {
                var self = this;
                this._super();

                //Set credit card number to credit card data object
                this.creditCardNumber.subscribe(function(value) {

                    // installments enabled ??
                    var allInstallments = self.getAllInstallments();

                    // what card is this ??
                    var creditcardType = creditCardData.creditCard.type;

                    if(creditcardType) {

                        if (creditcardType in allInstallments) {
                            // get for the creditcard the installments
                            var installmentCreditcard = allInstallments[creditcardType];
                            var grandTotal = quote.totals().grand_total;

                            var numberOfInstallments = 0;
                            $.each(installmentCreditcard, function (amount, installment) {
                                if(grandTotal <= amount) {
                                    numberOfInstallments = installment;
                                    return false;
                                }
                            });

                            if(numberOfInstallments > 0) {
                                installments.setInstallments(numberOfInstallments);
                            }
                        } else {
                            installments.setInstallments(0);
                        }
                    }
                });
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
                        'store_cc': this.setStoreCc(),
                        'number_of_installments': this.installment()
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
                if(customer.isLoggedIn()) {
                    return window.checkoutConfig.payment.adyenCc.canCreateBillingAgreement;
                }
                return false;
            },
            isShowLegend: function() {
                return true;
            },
            validate: function () {
                var form = 'form[data-role=adyen-cc-form]';

                var validate =  $(form).validation() && $(form).validation('isValid');
                // add extra validation because jqeury validation will not work on non name attributes
                var ccNumber = Boolean($(form + ' #creditCardNumber').valid());
                var owner = Boolean($(form + ' #creditCardHolderName').valid());
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
            },
            getIcons: function (type) {
                return window.checkoutConfig.payment.adyenCc.icons.hasOwnProperty(type)
                    ? window.checkoutConfig.payment.adyenCc.icons[type]
                    : false
            },
            hasInstallments: function() {
                return window.checkoutConfig.payment.adyenCc.hasInstallments;
            },
            getAllInstallments: function() {
                return window.checkoutConfig.payment.adyenCc.installments;
            }
        });
    }
);


