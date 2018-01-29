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
        'jquery',
        'ko',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/model/installments',
        'mage/url'
    ],
    function ($, ko, Component, customer, creditCardData, additionalValidators, quote, installments, url) {

        'use strict';
        var cvcLength = ko.observable(4);

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
            initialize: function () {
                var self = this;
                this._super();

                installments.setInstallments(0);


                // include dynamic cse javascript
                var dfScriptTag = document.createElement('script');
                dfScriptTag.src = this.getLibrarySource();
                dfScriptTag.type = "text/javascript";
                document.body.appendChild(dfScriptTag);

                //Set credit card number to credit card data object
                this.creditCardNumber.subscribe(function (value) {

                    // installments enabled ??
                    var allInstallments = self.getAllInstallments();

                    // what card is this ??

                    if (creditCardData.creditCard) {
                        var creditcardType = creditCardData.creditCard.type;

                        cvcLength(4);
                        if (creditcardType != "AE") {
                            cvcLength(3);
                        }
                        if (creditcardType in allInstallments) {

                            // get for the creditcard the installments
                            var installmentCreditcard = allInstallments[creditcardType];
                            var grandTotal = quote.totals().grand_total;

                            var numberOfInstallments = [];
                            var dividedAmount = 0;
                            var dividedString = "";
                            $.each(installmentCreditcard, function (amount, installment) {

                                if (grandTotal >= amount) {
                                    dividedAmount = (grandTotal / installment).toFixed(quote.getPriceFormat().precision);
                                    dividedString = installment + " x " + dividedAmount + " " + quote.totals().quote_currency_code;
                                    numberOfInstallments.push({
                                        key: [dividedString],
                                        value: installment
                                    });
                                }
                                else {
                                    return false;
                                }
                            });
                        }
                        if (numberOfInstallments) {
                            installments.setInstallments(numberOfInstallments);
                        }
                        else {
                            installments.setInstallments(0);
                        }
                    }
                });
            },
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getCode: function () {
                return 'adyen_cc';
            },
            getData: function () {
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
            getCvcLength: function () {
                return cvcLength();
            },
            isActive: function () {
                return true;
            },

            /**
             * Returns state of place order button
             * @returns {boolean}
            */
            isButtonActive: function() {
              return this.isActive() && this.getCode() == this.isChecked() && this.isPlaceOrderActionAllowed();
            },

            /**
             * @override
             */
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                var options = {};
                var cseInstance = adyen.createEncryption(options);
                var generationtime = this.getGenerationTime();

                var cardData = {
                    number: this.creditCardNumber(),
                    cvc: this.creditCardVerificationNumber(),
                    holderName: this.creditCardOwner(),
                    expiryMonth: this.creditCardExpMonth(),
                    expiryYear: this.creditCardExpYear(),
                    generationtime: generationtime
                };

                var data = cseInstance.encrypt(cardData);
                this.encryptedData(data);

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                        function () {
                            self.afterPlaceOrder();

                            if (self.redirectAfterPlaceOrder) {
                                // use custom redirect Link for supporting 3D secure
                                window.location.replace(url.build(window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl));
                            }
                        }
                    );

                    return true;
                }

                return false;
            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            context: function () {
                return this;
            },

            isCseEnabled: function () {
                return window.checkoutConfig.payment.adyenCc.cseEnabled;
            },
            getCSEKey: function () {
                return window.checkoutConfig.payment.adyenCc.cseKey;
            },
            getLibrarySource: function () {
                return window.checkoutConfig.payment.adyenCc.librarySource;

            },
            getGenerationTime: function () {
                return window.checkoutConfig.payment.adyenCc.generationTime;
            },
            canCreateBillingAgreement: function () {
                if (customer.isLoggedIn()) {
                    return window.checkoutConfig.payment.adyenCc.canCreateBillingAgreement;
                }
                return false;
            },
            isShowLegend: function () {
                return true;
            },
            validate: function () {
                var form = 'form[data-role=adyen-cc-form]';

                var validate = $(form).validation() && $(form).validation('isValid');

                // add extra validation because jquery validation will not work on non name attributes

                var ccNumber = Boolean($(form + ' #creditCardNumber').valid());
                var owner = Boolean($(form + ' #creditCardHolderName').valid());
                var expiration = Boolean($(form + ' #adyen_cc_expiration').valid());
                var expiration_yr = Boolean($(form + ' #adyen_cc_expiration_yr').valid());
                var cid = Boolean($(form + ' #adyen_cc_cc_cid').valid());

                if (!validate || !ccNumber || !owner || !expiration || !expiration_yr || !cid) {
                    return false;
                }

                return true;
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            getIcons: function (type) {
                return window.checkoutConfig.payment.adyenCc.icons.hasOwnProperty(type)
                    ? window.checkoutConfig.payment.adyenCc.icons[type]
                    : false
            },
            hasInstallments: function () {
                return window.checkoutConfig.payment.adyenCc.hasInstallments;
            },
            getAllInstallments: function () {
                return window.checkoutConfig.payment.adyenCc.installments;
            }
        });
    }
);


