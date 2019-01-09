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

        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/cc-form',
                creditCardOwner: '',
                setStoreCc: false,
                installment: '',
                creditCardDetailsValid: false
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'creditCardType',
                        'creditCardOwner',
                        'creditCardNumber',
                        'securityCode',
                        'expiryMonth',
                        'expiryYear',
                        'setStoreCc',
                        'installment',
                        'creditCardDetailsValid',
                        'variant',
                        'placeOrderAllowed'
                    ]);

                return this;
            },
            getInstallments: installments.getInstallments(),
            /**
             * Renders the secure fields,
             * creates the card component,
             * sets up the callbacks for card components and
             * set up the installments
             */
            renderSecureFields: function () {
                var self = this;
                self.placeOrderAllowed(false);
                if (!self.getOriginKey()) {
                    return;
                }


                installments.setInstallments(0);

                // installments enabled ??
                var allInstallments = self.getAllInstallments();
                var cardNode = document.getElementById('cardContainer');

                var checkout = new AdyenCheckout({
                    locale: self.getLocale()
                });

                var card = checkout.create('card', {
                    originKey: self.getOriginKey(),
                    loadingContext: self.getLoadingContext(),
                    type: 'card',
                    hasHolderName: true,
                    holderNameRequired: true,
                    groupTypes: self.getAvailableCardTypeAltCodes(),

                    onChange: function (state) {
                        // isValid is not present on start
                        if (typeof state.isValid !== 'undefined' && state.isValid === false) {
                            self.creditCardDetailsValid(false);
                            self.placeOrderAllowed(false);
                        }

                        // Define the card type
                        // translate adyen card type to magento card type
                        var creditCardType = self.getCcCodeByAltCode(state.brand);

                        if (creditCardType) {
                            // If the credit card type is already set, check if it changed or not
                            if (!self.creditCardType() || self.creditCardType() && self.creditCardType() != creditCardType) {
                                if (creditCardType in allInstallments) {

                                    // get for the creditcard the installments
                                    var installmentCreditcard = allInstallments[creditCardType];
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

                            // Color the image of the credit card
                            // for BCMC as this is not a core payment method inside magento use maestro as brand detection
                            if (creditCardType == "BCMC") {
                                self.creditCardType("MI");
                            } else {
                                self.creditCardType(creditCardType);

                            }
                        } else {
                            self.creditCardType("")
                        }
                    },
                    onValid: function (state) {
                        self.variant(state.brand);
                        self.creditCardNumber(state.data.encryptedCardNumber);
                        self.expiryMonth(state.data.encryptedExpiryMonth);
                        self.expiryYear(state.data.encryptedExpiryYear);
                        self.securityCode(state.data.encryptedSecurityCode);
                        self.creditCardOwner(state.data.holderName);
                        self.creditCardDetailsValid(true);
                        self.placeOrderAllowed(true);
                    },
                    onError: function (state) {
                        self.creditCardDetailsValid(false);
                        self.placeOrderAllowed(false);
                    }
                });

                card.mount(cardNode);
            },
            /**
             * Builds the payment details part of the payment information reqeust
             *
             * @returns {{method: *, additional_data: {cc_type: *, number: *, cvc, expiryMonth: *, expiryYear: *, holderName: *, store_cc: *, number_of_installments: *}}}
             */
            getData: function () {
                return {
                    'method': this.item.method,
                    additional_data: {
                        'card_brand': this.variant(),
                        'cc_type': this.creditCardType(),
                        'number': this.creditCardNumber(),
                        'cvc': this.securityCode(),
                        'expiryMonth': this.expiryMonth(),
                        'expiryYear': this.expiryYear(),
                        'holderName': this.creditCardOwner(),
                        'store_cc': this.setStoreCc(),
                        'number_of_installments': this.installment()
                    }
                };
            },
            /**
             * Returns state of place order button
             * @returns {boolean}
             */
            isButtonActive: function () {
                return this.isActive() && this.getCode() == this.isChecked() && this.isPlaceOrderActionAllowed() && this.placeOrderAllowed();
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

                if (event) {
                    event.preventDefault();
                }

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
            /**
             * Validates the payment date when clicking the pay button
             *
             * @returns {boolean}
             */
            validate: function () {
                var self = this;

                var form = 'form[data-role=adyen-cc-form]';

                var validate = $(form).validation() && $(form).validation('isValid');

                if (!validate) {
                    return false;
                }

                return true;
            },
            /**
             * Validates if the typed in card holder is valid
             * - length validation, can not be empty
             *
             * @returns {boolean}
             */
            isCardOwnerValid: function () {
                if (this.creditCardOwner().length == 0) {
                    return false;
                }

                return true;
            },
            /**
             * The card component send the card details validity in a callback which is saved in the
             * creditCardDetailsValid observable
             *
             * @returns {*}
             */
            isCreditCardDetailsValid: function () {
                return this.creditCardDetailsValid();
            },
            /**
             * Translates the card type alt code (used in Adyen) to card type code (used in Magento) if it's available
             *
             * @param altCode
             * @returns {*}
             */
            getCcCodeByAltCode: function (altCode) {
                var ccTypes = window.checkoutConfig.payment.ccform.availableTypesByAlt[this.getCode()];
                if (ccTypes.hasOwnProperty(altCode)) {
                    return ccTypes[altCode];
                }

                return "";
            },
            /**
             * Get available card types translated to the Adyen card type codes
             * (The card type alt code is the Adyen card type code)
             *
             * @returns {string[]}
             */
            getAvailableCardTypeAltCodes: function () {
                var ccTypes = window.checkoutConfig.payment.ccform.availableTypesByAlt[this.getCode()];
                return Object.keys(ccTypes);
            },
            /**
             * Return Payment method code
             *
             * @returns {*}
             */
            getCode: function () {
                return window.checkoutConfig.payment.adyenCc.methodCode;
            },
            getOriginKey: function () {
                return window.checkoutConfig.payment.adyenCc.originKey;
            },
            getLoadingContext: function () {
                return window.checkoutConfig.payment.adyenCc.checkoutUrl;
            },
            getLocale: function () {
                return window.checkoutConfig.payment.adyenCc.locale;
            },
            isActive: function () {
                return true;
            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
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
            },
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            context: function () {
                return this;
            }
        });
    }
);
