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
        'mage/url',
        'Magento_Vault/js/view/payment/vault-enabler',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Checkout/js/action/select-payment-method',
        'Adyen_Payment/js/threeds2-js-utils',
        'Adyen_Payment/js/model/threeds2',
        'Magento_Checkout/js/model/error-processor'
    ],
    function ($, ko, Component, customer, creditCardData, additionalValidators, quote, installmentsHelper, url, VaultEnabler, urlBuilder, storage, fullScreenLoader, setPaymentMethodAction, selectPaymentMethodAction, threeDS2Utils, threeds2, errorProcessor) {

        'use strict';

        return Component.extend({
            // need to duplicate as without the button will never activate on first time page view
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),

            defaults: {
                template: 'Adyen_Payment/payment/cc-form',
                creditCardOwner: '',
                storeCc: false,
                installment: '',
                creditCardDetailsValid: false
            },
            /**
             * @returns {exports.initialize}
             */
            initialize: function () {
                this._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
                this.vaultEnabler.isActivePaymentTokenEnabler(false);

                // initialize adyen component for general use
                this.checkout = new AdyenCheckout({
                    locale: this.getLocale()
                });

                return this;
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
                        'installment',
                        'installments',
                        'creditCardDetailsValid',
                        'placeOrderAllowed'
                    ]);

                return this;
            },
            /**
             * Returns true if card details can be stored
             * @returns {*|boolean}
             */
            getEnableStoreDetails: function () {
                return this.canCreateBillingAgreement() && !this.isVaultEnabled();
            },
            /**
             * Renders the secure fields,
             * creates the card component,
             * sets up the callbacks for card components and
             * set up the installments
             */
            renderSecureFields: function () {
                let self = this;

                if (!self.getOriginKey()) {
                    return;
                }

                self.installments(0);

                // installments
                let allInstallments = self.getAllInstallments();
                let cardNode = document.getElementById('cardContainer');

                self.cardComponent = self.checkout.create('card', {
                    originKey: self.getOriginKey(),
                    environment: self.getCheckoutEnvironment(),
                    type: 'card',
                    hasHolderName: true,
                    holderNameRequired: true,
                    enableStoreDetails: self.getEnableStoreDetails(),
                    groupTypes: self.getAvailableCardTypeAltCodes(),

                    onChange: function (state, component) {
                        if (!!state.isValid && !component.state.errors.encryptedSecurityCode) {
                            self.storeCc = !!state.data.storePaymentMethod;
                            self.creditCardNumber(state.data.paymentMethod.encryptedCardNumber);
                            self.expiryMonth(state.data.paymentMethod.encryptedExpiryMonth);
                            self.expiryYear(state.data.paymentMethod.encryptedExpiryYear);
                            self.securityCode(state.data.paymentMethod.encryptedSecurityCode);
                            self.creditCardOwner(state.data.paymentMethod.holderName);
                            self.creditCardDetailsValid(true);
                            self.placeOrderAllowed(true);
                        } else {
                            self.creditCardDetailsValid(false);
                            self.placeOrderAllowed(false);
                        }
                    },
                    onBrand: function (state) {
                        // Define the card type
                        // translate adyen card type to magento card type
                        var creditCardType = self.getCcCodeByAltCode(state.brand);
                        if (creditCardType) {
                            // If the credit card type is already set, check if it changed or not
                            if (!self.creditCardType() || self.creditCardType() && self.creditCardType() != creditCardType) {
                                let numberOfInstallments = [];

                                if (creditCardType in allInstallments) {

                                    // get for the creditcard the installments
                                    let installmentCreditcard = allInstallments[creditCardType];
                                    let grandTotal = quote.totals().grand_total;
                                    let precision = quote.getPriceFormat().precision;
                                    let currencyCode = quote.totals().quote_currency_code;

                                    numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(installmentCreditcard, grandTotal, precision, currencyCode);
                                }

                                if (numberOfInstallments) {
                                    self.installments(numberOfInstallments);
                                }
                                else {
                                    self.installments(0);
                                }
                            }

                            // for BCMC as this is not a core payment method inside magento use maestro as brand detection
                            if (creditCardType == "BCMC") {
                                self.creditCardType("MI");
                            } else {
                                self.creditCardType(creditCardType);

                            }
                        } else {
                            self.creditCardType("")
                            self.installments(0);
                        }
                    }
                }).mount(cardNode);
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
            renderThreeDS2Component: function (type, token) {
                var self = this;
                var threeDS2Node = document.getElementById('threeDS2Container');

                if (type == "IdentifyShopper") {
                    self.threeDS2IdentifyComponent = self.checkout
                        .create('threeDS2DeviceFingerprint', {
                            fingerprintToken: token,
                            onComplete: function (result) {
                                self.threeDS2IdentifyComponent.unmount();
                                threeds2.processThreeDS2(result.data).done(function (responseJSON) {
                                    self.validateThreeDS2OrPlaceOrder(responseJSON)
                                }).fail(function (result) {
                                    errorProcessor.process(result, self.messageContainer);
                                    self.isPlaceOrderActionAllowed(true);
                                    fullScreenLoader.stopLoader();
                                });
                            },
                            onError: function (error) {
                                console.log(JSON.stringify(error));
                            }
                        });

                    self.threeDS2IdentifyComponent.mount(threeDS2Node);


                } else if (type == "ChallengeShopper") {
                    fullScreenLoader.stopLoader();

                    var popupModal = $('#threeDS2Modal').modal({
                        // disable user to hide popup
                        clickableOverlay: false,
                        // empty buttons, we don't need that
                        buttons: [],
                        modalClass: 'threeDS2Modal'
                    });


                    popupModal.modal("openModal");

                    self.threeDS2ChallengeComponent = self.checkout
                        .create('threeDS2Challenge', {
                            challengeToken: token,
                            size: '05',
                            onComplete: function (result) {
                                self.threeDS2ChallengeComponent.unmount();
                                self.closeModal(popupModal);

                                fullScreenLoader.startLoader();
                                threeds2.processThreeDS2(result.data).done(function (responseJSON) {
                                    self.validateThreeDS2OrPlaceOrder(responseJSON);
                                }).fail(function (result) {
                                    errorProcessor.process(result, self.messageContainer);
                                    self.isPlaceOrderActionAllowed(true);
                                    fullScreenLoader.stopLoader();
                                });
                            },
                            onError: function (error) {
                                console.log(JSON.stringify(error));
                            }
                        });
                    self.threeDS2ChallengeComponent.mount(threeDS2Node);
                }
            },
            /**
             * This method is a workaround to close the modal in the right way and reconstruct the threeDS2Modal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function (popupModal) {
                popupModal.modal("closeModal");
                $('.threeDS2Modal').remove();
                $('.modals-overlay').remove();
                $('body').removeClass('_has-modal');

                // reconstruct the threeDS2Modal container again otherwise component can not find the threeDS2Modal
                $('#threeDS2Wrapper').append("<div id=\"threeDS2Modal\">" +
                    "<div id=\"threeDS2Container\"></div>" +
                    "</div>");
            },
            /**
             * Get data for place order
             * @returns {{method: *}}
             */
            getData: function () {
                const browserInfo = threeDS2Utils.getBrowserInfo();

                var data = {
                    'method': this.item.method,
                    additional_data: {
                        'guestEmail': quote.guestEmail,
                        'cc_type': this.creditCardType(),
                        'number': this.creditCardNumber(),
                        'cvc': this.securityCode(),
                        'expiryMonth': this.expiryMonth(),
                        'expiryYear': this.expiryYear(),
                        'holderName': this.creditCardOwner(),
                        'store_cc': this.storeCc,
                        'number_of_installments': this.installment(),
                        'java_enabled': browserInfo.javaEnabled,
                        'screen_color_depth': browserInfo.colorDepth,
                        'screen_width': browserInfo.screenWidth,
                        'screen_height': browserInfo.screenHeight,
                        'timezone_offset': browserInfo.timeZoneOffset,
                        'language': browserInfo.language
                    }
                };
                this.vaultEnabler.visitAdditionalData(data);
                return data;
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
                    fullScreenLoader.startLoader();
                    self.isPlaceOrderActionAllowed(false);

                    self.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                fullScreenLoader.stopLoader();
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                        function (response) {
                            self.afterPlaceOrder();
                            self.validateThreeDS2OrPlaceOrder(response);
                        }
                    );
                }
                return false;
            },
            /**
             * Based on the response we can start a 3DS2 validation or place the order
             * @param responseJSON
             */
            validateThreeDS2OrPlaceOrder: function (responseJSON) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.threeDS2) {
                    // render component
                    self.renderThreeDS2Component(response.type, response.token);
                } else {
                    window.location.replace(url.build(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl)
                    );
                }
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
            getCheckoutEnvironment: function () {
                return window.checkoutConfig.payment.adyenCc.checkoutEnvironment;
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
            },
            /**
             * @returns {Bool}
             */
            isVaultEnabled: function () {
                return this.vaultEnabler.isVaultEnabled();
            },
            /**
             * @returns {String}
             */
            getVaultCode: function () {
                return window.checkoutConfig.payment[this.getCode()].vaultCode;
            }
        });
    }
);
