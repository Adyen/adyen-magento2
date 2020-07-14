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
        'mage/url',
        'Magento_Vault/js/view/payment/vault-enabler',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Adyen_Payment/js/model/adyen-configuration'
    ],
    function (
        $,
        ko,
        Component,
        customer,
        creditCardData,
        additionalValidators,
        quote,
        url,
        VaultEnabler,
        urlBuilder,
        storage,
        fullScreenLoader,
        setPaymentMethodAction,
        selectPaymentMethodAction,
        errorProcessor,
        adyenPaymentService,
        adyenConfiguration
    ) {

        'use strict';

        return Component.extend({
            // need to duplicate as without the button will never activate on first time page view
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            comboCardOption: ko.observable('credit'),

            defaults: {
                template: 'Adyen_Payment/payment/cc-form',
                installment: '',
                stateData: {},
                checkoutComponent: {},
                cardComponent: {}
            },
            /**
             * @returns {exports.initialize}
             */
            initialize: function () {
                this._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
                this.vaultEnabler.isActivePaymentTokenEnabler(false);
                this.checkoutComponent = adyenPaymentService.getCheckoutComponent();

                return this;
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'installment',
                        'placeOrderAllowed'
                    ]);

                return this;
            },
            /**
             * Returns true if card details can be stored
             * The user is logged in and
             * Billing agreement or vault is ebabled
             *
             * @returns {*|boolean}
             */
            getEnableStoreDetails: function () {
                if (customer.isLoggedIn()) {
                    // TODO create new configuration to enable stored details without vault and billing agreement
                    // since we noew use the payment Methods response to fetch the stored payment methods
                    return this.canCreateBillingAgreement() || this.isVaultEnabled();
                }

                return false;
            },
            /**
             * Renders checkout card component
             */
            renderCardComponent: function () {
                if (!adyenConfiguration.getOriginKey()) {
                    return;
                }

                var self = this;
                // installments configuration
                var installmentsConfiguration = this.getAllInstallments();
                // TODO get config from admin configuration
                installmentsConfiguration = []; // DUmmy data for testing

                var placeOrderAllowed = self.placeOrderAllowed.bind(self);

                function handleOnChange(state, component) {
                    if (!!state.isValid) {
                        self.stateData = state.data;
                        placeOrderAllowed(true);
                    } else {
                        placeOrderAllowed(false);
                    }
                };

                // Extra configuration object for card payments
                const configuration = {
                    hasHolderName: true,
                    holderNameRequired: true,
                    enableStoreDetails: self.getEnableStoreDetails(),
                    groupTypes: self.getAvailableCardTypeAltCodes(),
                    installmentOptions: installmentsConfiguration,
                    onChange: handleOnChange
                };

                // create and mount
                this.cardComponent = this.checkoutComponent.create('card', configuration).mount('#cardContainer');
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
            renderThreeDS2Component: function (action, orderId) {
                var self = this;

                // Handle identify shopper action
                if (action.type == 'threeDS2Fingerprint') {
                    var configuration = {
                        onAdditionalDetails: function (result) {
                            var request = result.data;
                            request.orderId = orderId;
                            adyenPaymentService.processThreeDS2(request).done(function (responseJSON) {
                                self.validateThreeDS2OrPlaceOrder(responseJSON, orderId)
                            }).fail(function (result) {
                                errorProcessor.process(result, self.messageContainer);
                                self.isPlaceOrderActionAllowed(true);
                                fullScreenLoader.stopLoader();
                            });
                        }
                    };
                }

                // Handle challenge shopper action
                if (action.type == "threeDS2Challenge") {
                    fullScreenLoader.stopLoader();

                    var popupModal = $('#threeDS2Modal').modal({
                        // disable user to hide popup
                        clickableOverlay: false,
                        responsive: true,
                        innerScroll: false,
                        // empty buttons, we don't need that
                        buttons: [],
                        modalClass: 'threeDS2Modal'
                    });

                    popupModal.modal("openModal");

                    var configuration = {
                        size: '05',
                        onAdditionalDetails: function (result) {
                            self.closeModal(popupModal);
                            fullScreenLoader.startLoader();
                            var request = result.data;
                            request.orderId = orderId;
                            adyenPaymentService.processThreeDS2(request).done(function (responseJSON) {
                                self.validateThreeDS2OrPlaceOrder(responseJSON, orderId);
                            }).fail(function (result) {
                                errorProcessor.process(result, self.messageContainer);
                                self.isPlaceOrderActionAllowed(true);
                                fullScreenLoader.stopLoader();
                            });
                        }
                    };
                }

                self.checkoutComponent.createFromAction(action, configuration).mount('#threeDS2Container');
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
                var data = {
                    'method': this.item.method,
                    additional_data: {
                        'state_data': JSON.stringify(this.stateData),
                        'combo_card_type': this.comboCardOption(),
                        'channel': 'Web' //TODO pass channel from frontend
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
                            function (orderId) {
                                self.afterPlaceOrder();
                                adyenPaymentService.getOrderPaymentStatus(orderId)
                                .done(function (responseJSON) {
                                    self.validateThreeDS2OrPlaceOrder(responseJSON, orderId)
                                });
                            }
                        );
                }
                return false;
            },
            /**
             * Based on the response we can start a 3DS2 validation or place the order
             * @param responseJSON
             */
            validateThreeDS2OrPlaceOrder: function (responseJSON, orderId) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.type && (
                    response.type == "threeDS2Fingerprint" ||
                    response.type == "threeDS2Challenge"
                )) {
                    // render component
                    self.renderThreeDS2Component(response, orderId);
                } else {
                    window.location.replace(url.build(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl
                    ));
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
                return adyenConfiguration.getOriginKey;
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
            //TODO create configuration for this on admin
            isShowLegend: function () {
                return true;
            },
            //TODO create a configuration for information on admin
            getLegend: function () {
                return '';
            },
            showLogo: function () {
                return adyenConfiguration.showLogo;
            },
            /**
             *
             * @param type
             * @returns {*}
             */
            getIcons: function (type) {
                return window.checkoutConfig.payment.adyenCc.icons.hasOwnProperty(type)
                    ? window.checkoutConfig.payment.adyenCc.icons[type]
                    : false
            },
            /**
             *
             * @returns {any}
             */
            hasInstallments: function () {
                return this.comboCardOption() === 'credit' && window.checkoutConfig.payment.adyenCc.hasInstallments;
            },
            /**
             *
             * @returns {*}
             */
            getAllInstallments: function () {
                return window.checkoutConfig.payment.adyenCc.installments;
            },
            /**
             * @returns {*|boolean}
             */
            areComboCardsEnabled: function () {
                if (quote.billingAddress() === null) {
                    return false;
                }
                var countryId = quote.billingAddress().countryId;
                var currencyCode = quote.totals().quote_currency_code;
                var allowedCurrenciesByCountry = {
                    'BR': 'BRL',
                    'MX': 'MXN'
                };
                return allowedCurrenciesByCountry[countryId] &&
                    currencyCode === allowedCurrenciesByCountry[countryId];
            },
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            /**
             * @returns {exports}
             */
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
