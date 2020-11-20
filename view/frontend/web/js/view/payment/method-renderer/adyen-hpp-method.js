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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'uiLayout',
        'Magento_Ui/js/model/messages',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/bundle',
        'Adyen_Payment/js/model/adyen-configuration',
    ],
    function(
        ko,
        $,
        Component,
        selectPaymentMethodAction,
        quote,
        checkoutData,
        additionalValidators,
        adyenPaymentService,
        fullScreenLoader,
        placeOrderAction,
        layout,
        Messages,
        errorProcessor,
        AdyenComponent,
        adyenConfiguration,
    ) {
        'use strict';

        var unsupportedPaymentMethods = [
            'scheme',
            'boleto',
            'bcmc_mobile_QR',
            'wechatpay',
            /^bcmc$/,
            'applepay',
            'paywithgoogle'];
        var popupModal;
        var brandCode = ko.observable(null);
        var paymentMethod = ko.observable(null);

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/hpp-form',
                orderId: 0,
                paymentMethods: {}
            },
            initObservable: function() {
                this._super().observe([
                    'brandCode',
                    'paymentMethod',
                    'adyenPaymentMethods'
                ]);
                return this;
            },
            initialize: function() {
                var self = this;
                this._super();

                fullScreenLoader.startLoader();

                var paymentMethodsObserver = adyenPaymentService.getPaymentMethods();

                paymentMethodsObserver.subscribe(function(paymentMethodsResponse) {
                    self.loadAdyenPaymentMethods(paymentMethodsResponse);
                });

                self.loadAdyenPaymentMethods(paymentMethodsObserver());
            },
            loadAdyenPaymentMethods: function(paymentMethodsResponse) {
                var self = this;

                if (!!paymentMethodsResponse) {
                    var paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;

                    this.checkoutComponent = new AdyenCheckout({
                            hasHolderName: true,
                            locale: adyenConfiguration.getLocale(),
                            originKey: adyenConfiguration.getOriginKey(),
                            environment: adyenConfiguration.getCheckoutEnvironment(),
                            paymentMethodsResponse: paymentMethodsResponse.paymentMethodsResponse,
                            onAdditionalDetails: this.handleOnAdditionalDetails.bind(
                                this),
                        },
                    );

                    // Needed until the new ratepay component is released
                    if (JSON.stringify(paymentMethods).indexOf('ratepay') >
                        -1) {
                        var ratePayId = window.checkoutConfig.payment.adyenHpp.ratePayId;
                        var dfValueRatePay = self.getRatePayDeviceIdentToken();

                        // TODO check if still needed with checkout component
                        window.di = {
                            t: dfValueRatePay.replace(':', ''),
                            v: ratePayId,
                            l: 'Checkout',
                        };

                        // Load Ratepay script
                        var ratepayScriptTag = document.createElement('script');
                        ratepayScriptTag.src = '//d.ratepay.com/' + ratePayId +
                            '/di.js';
                        ratepayScriptTag.type = 'text/javascript';
                        document.body.appendChild(ratepayScriptTag);
                    }

                    self.adyenPaymentMethods(self.getAdyenHppPaymentMethods(paymentMethodsResponse));
                    fullScreenLoader.stopLoader();
                }
            },
            getAdyenHppPaymentMethods: function(paymentMethodsResponse) {
                var self = this;

                var paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;

                var paymentList = _.reduce(paymentMethods,
                    function(accumulator, paymentMethod) {

                        if (!self.isPaymentMethodSupported(
                            paymentMethod.type)) {
                            return accumulator;
                        }

                        var messageContainer = new Messages();
                        var name = 'messages-' +
                            self.getBrandCodeFromPaymentMethod(paymentMethod);
                        var messagesComponent = {
                            parent: self.name,
                            name: name,
                            displayArea: name,
                            component: 'Magento_Ui/js/view/messages',
                            config: {
                                messageContainer: messageContainer,
                            },
                        };
                        layout([messagesComponent]);

                        var result = {};

                        /**
                         * Returns the payment method's brand code (in checkout api it is the type)
                         * @returns {*}
                         */
                        result.getBrandCode = function() {
                            return self.getBrandCodeFromPaymentMethod(
                                paymentMethod);
                        };

                        result.brandCode = result.getBrandCode();
                        result.name = paymentMethod.name;
                        result.icon = {}; // TODO get icon details

                        result.method = self.item.method;
                        /**
                         * Observable to enable and disable place order buttons for payment methods
                         * Default value is true to be able to send the real hpp requiests that doesn't require any input
                         * @type {observable}
                         */
                        result.placeOrderAllowed = ko.observable(true);
                        result.getCode = function() {
                            return self.item.method;
                        };
                        result.getMessageName = function() {
                            return 'messages-' +
                                self.getBrandCodeFromPaymentMethod(
                                    paymentMethod);
                        };
                        result.getMessageContainer = function() {
                            return messageContainer;
                        };
                        result.validate = function() {
                            return self.validate(result.getBrandCode());
                        };
                        result.placeRedirectOrder = function placeRedirectOrder(data) {

                            // Place Order but use our own redirect url after
                            fullScreenLoader.startLoader();
                            $('.hpp-message').slideUp();
                            self.isPlaceOrderActionAllowed(false);

                            $.when(
                                placeOrderAction(data,
                                    self.currentMessageContainer),
                            ).fail(
                                function(response) {
                                    self.isPlaceOrderActionAllowed(true);
                                    fullScreenLoader.stopLoader();
                                    self.showErrorMessage(response);
                                },
                            ).done(
                                function(orderId) {
                                    self.afterPlaceOrder();
                                    adyenPaymentService.getOrderPaymentStatus(
                                        orderId).
                                        done(function(responseJSON) {
                                            self.validateActionOrPlaceOrder(
                                                responseJSON,
                                                orderId);
                                        });
                                },
                            );
                        };

                        /**
                         * Set and get if the place order action is allowed
                         * Sets the placeOrderAllowed observable and the original isPlaceOrderActionAllowed as well
                         * @param bool
                         * @returns {*}
                         */
                        result.isPlaceOrderAllowed = function(bool) {
                            self.isPlaceOrderActionAllowed(bool);
                            return result.placeOrderAllowed(bool);
                        };
                        result.afterPlaceOrder = function() {
                            return self.afterPlaceOrder();
                        };

                        /**
                         * Renders the secure fields,
                         * creates the ideal component,
                         * sets up the callbacks for ideal components and
                         */
                        result.renderCheckoutComponent = function() {
                            result.isPlaceOrderAllowed(false);

                            var showPayButton = false;
                            const showPayButtonPaymentMethods = [
                                'paypal',
                                'applePay',
                                'googlePay'
                            ];

                            if (showPayButtonPaymentMethods.includes(
                                paymentMethod.type)) {
                                showPayButton = true;
                            }

                            // TODO take the terms and confitions magento checkbox into account as well
                            // If the details are empty and the pay button does not needs to be rendered by the component
                            // simply skip rendering the adyen checkout component
                            if (!paymentMethod.details && !showPayButton) {
                                result.isPlaceOrderAllowed(true);
                                return;
                            }

                            var city = '';
                            var country = '';
                            var postalCode = '';
                            var street = '';
                            var firstName = '';
                            var lastName = '';
                            var telephone = '';
                            var email = '';
                            var shopperGender = '';
                            var shopperDateOfBirth = '';

                            if (!!quote && !!quote.shippingAddress()) {
                                city = quote.shippingAddress().city;
                                country = quote.shippingAddress().countryId;
                                postalCode = quote.shippingAddress().postcode;
                                street = quote.shippingAddress().
                                    street.
                                    join(' ');
                                firstName = quote.shippingAddress().firstname;
                                lastName = quote.shippingAddress().lastname;
                                telephone = quote.shippingAddress().telephone;

                                if (!!customerData.email) {
                                    email = customerData.email;
                                } else if (!!quote.guestEmail) {
                                    email = quote.guestEmail;
                                }

                                shopperGender = customerData.gender;
                                shopperDateOfBirth = customerData.dob;
                            }

                            function getAdyenGender(gender) {
                                if (gender == 1) {
                                    return 'MALE';
                                } else if (gender == 2) {
                                    return 'FEMALE';
                                }
                                return 'UNKNOWN';

                            }

                            /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
                            var configuration = Object.assign(paymentMethod, {
                                showPayButton: showPayButton,
                                countryCode: country,
                                currencyCode: quote.totals().quote_currency_code,
                                amount: quote.totals().grand_total, //TODO minor units and PW-2029 adjustment
                                data: {
                                    personalDetails: {
                                        firstName: firstName,
                                        lastName: lastName,
                                        telephoneNumber: telephone,
                                        shopperEmail: email,
                                        gender: getAdyenGender(shopperGender),
                                        dateOfBirth: shopperDateOfBirth,
                                    },
                                    billingAddress: {
                                        city: city,
                                        country: country,
                                        houseNumberOrName: '',
                                        postalCode: postalCode,
                                        street: street,
                                    },
                                },
                                onChange: function(state) {
                                    result.isPlaceOrderAllowed(state.isValid);
                                }
                            });

                            try {
                                result.component = self.checkoutComponent.create(
                                    result.getBrandCode(), configuration).
                                    mount(
                                        '#adyen-alternative-payment-container-' +
                                        result.getBrandCode());
                            } catch (err) {
                                console.log(err);
                                // The component does not exist yet
                            }
                        };
                        // TODO do the same way as the card payments
                        result.continueToAdyenBrandCode = function() {
                            // set payment method to adyen_hpp
                            var self = this;

                            if (this.validate() &&
                                additionalValidators.validate()) {
                                var data = {};
                                data.method = self.method;

                                var additionalData = {};
                                additionalData.brand_code = self.brandCode;

                                let stateData;
                                if ('component' in self) {
                                    stateData = self.component.data;
                                } else {
                                    stateData = {
                                        paymentMethod: {
                                            type: self.brandCode
                                        }
                                    };
                                }

                                additionalData.stateData = JSON.stringify(stateData);

                                if (brandCode() == 'ratepay') {
                                    additionalData.df_value = this.getRatePayDeviceIdentToken();
                                }

                                data.additional_data = additionalData;
                                this.placeRedirectOrder(data);
                            }

                            return false;
                        };

                        result.getRatePayDeviceIdentToken = function() {
                            return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
                        };

                        accumulator.push(result);
                        return accumulator;
                    }, []);

                return paymentList;
            },
            /**
             * Some payment methods we do not want to render as it requires extra implementation
             * or is already implemented in a separate payment method.
             * Using a match as we want to prevent to render all Boleto and most of the WeChat types
             * @param paymentMethod
             * @returns {boolean}
             */
            isPaymentMethodSupported: function(paymentMethod) {
                if (paymentMethod == 'wechatpayWeb') {
                    return true;
                }
                for (var i = 0; i < unsupportedPaymentMethods.length; i++) {
                    var match = paymentMethod.match(
                        unsupportedPaymentMethods[i]);
                    if (match) {
                        return false;
                    }
                }
                return true;
            },
            selectPaymentMethodBrandCode: function() {
                var self = this;

                // set payment method to adyen_hpp
                var data = {
                    'method': self.method,
                    'po_number': null,
                    'additional_data': {
                        brand_code: self.brandCode,
                    },
                };

                // set the brandCode
                brandCode(self.brandCode);

                // set payment method
                paymentMethod(self.method);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },
            /**
             * This method is a workaround to close the modal in the right way and reconstruct the ActionModal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function(popupModal) {
                popupModal.modal('closeModal');
                $('.ActionModal').remove();
                $('.modals-overlay').remove();
                $('body').removeClass('_has-modal');

                // reconstruct the ActionModal container again otherwise component can not find the ActionModal
                $('#ActionWrapper').append('<div id="ActionModal">' +
                    '<div id="ActionContainer"></div>' +
                    '</div>');
            },
            isBrandCodeChecked: ko.computed(function() {

                if (!quote.paymentMethod()) {
                    return null;
                }

                if (quote.paymentMethod().method == paymentMethod()) {
                    return brandCode();
                }
                return null;
            }),
            /**
             * Based on the response we can start a action component or redirect
             * @param responseJSON
             */
            validateActionOrPlaceOrder: function(responseJSON, orderId) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the redirectUrl
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl,
                    );
                } else {
                    // render component
                    self.orderId = orderId;
                    self.renderActionComponent(response.action);
                }
            },

            renderActionComponent: function(action) {
                var self = this;
                var actionNode = document.getElementById('ActionContainer');

                fullScreenLoader.stopLoader();

                self.popupModal = $('#ActionModal').modal({
                    // disable user to hide popup
                    clickableOverlay: false,
                    responsive: true,
                    innerScroll: false,
                    // empty buttons, we don't need that
                    buttons: [],
                    modalClass: 'ActionModal',
                });

                self.popupModal.modal('openModal');
                self.actionComponent = self.checkoutComponent.createFromAction(
                    action).mount(actionNode);
            },
            handleOnAdditionalDetails: function(state, component) {
                var self = this;

                // call endpoint with state.data
                var request = state.data;
                request.orderId = self.orderId;

                // Using the same processor as 3DS2, refactor to generic name in a upcomming release will be breaking change for merchants.
                adyenPaymentService.paymentDetails(request).done(function() {
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl,
                    );
                }).fail(function(response) {
                    fullScreenLoader.stopLoader();
                    self.closeModal(self.popupModal);
                    errorProcessor.process(response,
                        self.currentMessageContainer);
                    self.isPlaceOrderActionAllowed(true);
                    self.showErrorMessage(response);
                });
            },
            /**
             * Issue with the default currentMessageContainer needs to be resolved for now just throw manually the eror message
             * @param response
             */
            showErrorMessage: function(response) {
                if (!!response['responseJSON'].parameters) {
                    $('#messages-' + brandCode()).
                        text((response['responseJSON'].message).replace('%1',
                            response['responseJSON'].parameters[0])).
                        slideDown();
                } else {
                    $('#messages-' + brandCode()).
                        text(response['responseJSON'].message).
                        slideDown();
                }

                setTimeout(function() {
                    $('#messages-' + brandCode()).slideUp();
                }, 10000);
            },
            validate: function(brandCode) {
                var form = '#payment_form_' + this.getCode() + '_' + brandCode;
                var validate = $(form).validation() &&
                    $(form).validation('isValid');

                if (!validate) {
                    return false;
                }

                return true;
            },
            /**
             * Returns the payment method's brand code using the payment method from the response object
             * (in checkout api it is the type)
             * @returns {*}
             */
            getBrandCodeFromPaymentMethod: function(paymentMethod) {
                if (typeof paymentMethod.type !== 'undefined') {
                    return paymentMethod.type;
                }

                return '';
            },

            getRatePayDeviceIdentToken: function() {
                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
            },
        });
    },
);
