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

        // Exlude from the alternative payment methods rendering process
        var unsupportedPaymentMethods = [
            'scheme',
            'boleto',
            'wechatpay',
            'ratepay'];

        var popupModal;
        var selectedAlternativePaymentMethodType = ko.observable(null);
        var paymentMethod = ko.observable(null);

        return Component.extend({
            self: this,
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            defaults: {
                template: 'Adyen_Payment/payment/hpp-form',
                orderId: 0,
                paymentMethods: {},
                handleActionPaymentMethods: ['paypal'],
            },
            initObservable: function() {
                this._super().observe([
                    'selectedAlternativePaymentMethodType',
                    'paymentMethod',
                    'adyenPaymentMethods',
                ]);
                return this;
            },
            initialize: function() {
                var self = this;
                this._super();

                fullScreenLoader.startLoader();

                var paymentMethodsObserver = adyenPaymentService.getPaymentMethods();

                // Subscribe to any further changes (shipping address might change on the payment page)
                paymentMethodsObserver.subscribe(
                    function(paymentMethodsResponse) {
                        self.loadAdyenPaymentMethods(paymentMethodsResponse);
                    });

                self.loadAdyenPaymentMethods(paymentMethodsObserver());
            },
            loadAdyenPaymentMethods: function(paymentMethodsResponse) {
                var self = this;

                if (!!paymentMethodsResponse.paymentMethodsResponse) {
                    var paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;
                    this.checkoutComponent = new AdyenCheckout({
                            hasHolderName: true,
                            locale: adyenConfiguration.getLocale(),
                            clientKey: adyenConfiguration.getClientKey(),
                            environment: adyenConfiguration.getCheckoutEnvironment(),
                            paymentMethodsResponse: paymentMethodsResponse.paymentMethodsResponse,
                            onAdditionalDetails: this.handleOnAdditionalDetails.bind(
                                this),
                            onCancel: this.handleOnCancel.bind(this),
                            onSubmit: this.handleOnSubmit.bind(this),
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

                    self.adyenPaymentMethods(
                        self.getAdyenHppPaymentMethods(paymentMethodsResponse));
                    fullScreenLoader.stopLoader();
                }
            },
            getAdyenHppPaymentMethods: function(paymentMethodsResponse) {
                var self = this;

                const showPayButtonPaymentMethods = [
                    'paypal',
                    'applepay',
                    'paywithgoogle',
                ];

                var paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;
                var paymentMethodsExtraInfo = paymentMethodsResponse.paymentMethodsExtraDetails;

                var paymentList = _.reduce(paymentMethods,
                    function(accumulator, paymentMethod) {

                        if (!self.isPaymentMethodSupported(
                            paymentMethod.type)) {
                            return accumulator;
                        }

                        var messageContainer = new Messages();
                        var name = 'messages-' + paymentMethod.type;
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

                        var result = {
                            isAvailable: ko.observable(true),
                            paymentMethod: paymentMethod,
                            method: self.item.method,
                            item: {
                                "title": paymentMethod.name,
                                "method": paymentMethod.type
                            },
                            /**
                             * Observable to enable and disable place order buttons for payment methods
                             * Default value is true to be able to send the real hpp requiests that doesn't require any input
                             * @type {observable}
                             */
                            placeOrderAllowed: ko.observable(true),
                            icon: !!paymentMethodsExtraInfo[paymentMethod.type]
                                ? paymentMethodsExtraInfo[paymentMethod.type].icon
                                : {},
                            getMessageName: function() {
                                return 'messages-' + paymentMethod.type;
                            },
                            getMessageContainer: function() {
                                return messageContainer;
                            },
                            validate: function() {
                                return self.validate(paymentMethod.type);
                            },
                            /**
                             * Set and get if the place order action is allowed
                             * Sets the placeOrderAllowed observable and the original isPlaceOrderActionAllowed as well
                             * @param bool
                             * @returns {*}
                             */
                            isPlaceOrderAllowed: function(bool) {
                                self.isPlaceOrderActionAllowed(bool);
                                return result.placeOrderAllowed(bool);
                            },
                            afterPlaceOrder: function() {
                                return self.afterPlaceOrder();
                            },
                            showPlaceOrderButton: function() {
                                if (showPayButtonPaymentMethods.includes(
                                    paymentMethod.type)) {
                                    return false;
                                }

                                return true;
                            },
                            renderCheckoutComponent: function() {
                                result.isPlaceOrderAllowed(false);

                                var showPayButton = false;

                                if (showPayButtonPaymentMethods.includes(
                                    paymentMethod.type)) {
                                    showPayButton = true;
                                }

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
                                var configuration = Object.assign(paymentMethod,
                                    {
                                        showPayButton: showPayButton,
                                        countryCode: country,
                                        data: {
                                            personalDetails: {
                                                firstName: firstName,
                                                lastName: lastName,
                                                telephoneNumber: telephone,
                                                shopperEmail: email,
                                                gender: getAdyenGender(
                                                    shopperGender),
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
                                            result.isPlaceOrderAllowed(
                                                state.isValid);
                                        },
                                        onClick: function(resolve, reject) {
                                            // for paypal add a workaround, remove when component fixes it
                                            if (selectedAlternativePaymentMethodType() ===
                                                'paypal') {
                                                return self.validate();
                                            } else {
                                                if (self.validate()) {
                                                    resolve();
                                                } else {
                                                    reject();
                                                }
                                            }
                                        },
                                    });

                                // Use extra configuration from the paymentMethodsExtraInfo object if available
                                if (paymentMethod.type in paymentMethodsExtraInfo && 'configuration' in paymentMethodsExtraInfo[paymentMethod.type]) {
                                    configuration = Object.assign(configuration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                                }

                                // Extra apple pay configuration
                                if (paymentMethod.type.includes('applepay')) {
                                    if ('configuration' in configuration &&
                                        'merchantName' in configuration.configuration) {
                                        configuration.totalPriceLabel = configuration.configuration.merchantName;
                                    }
                                }

                                try {
                                    const component = self.checkoutComponent.create(
                                        paymentMethod.type, configuration);
                                    const containerId = '#adyen-alternative-payment-container-' +
                                        paymentMethod.type;

                                    if ('isAvailable' in component) {
                                        component.isAvailable().then(() => {
                                            component.mount(containerId);
                                        }).catch(e => {
                                            result.isAvailable(false);
                                            console.log(paymentMethod.type +
                                                ' is not available, the method will be hidden from the payment list');
                                        });
                                    } else {
                                        component.mount(containerId);
                                    }

                                    result.component = component;

                                } catch (err) {
                                    // The component does not exist yet
                                    console.log(err);
                                }
                            },
                            placeOrder: function() {
                                var innerSelf = this;

                                if (innerSelf.validate()) {
                                    var data = {};
                                    data.method = innerSelf.method;

                                    var additionalData = {};
                                    additionalData.brand_code = selectedAlternativePaymentMethodType();

                                    let stateData;
                                    if ('component' in innerSelf) {
                                        stateData = innerSelf.component.data;
                                    } else {
                                        stateData = {
                                            paymentMethod: {
                                                type: selectedAlternativePaymentMethodType(),
                                            },
                                        };
                                    }

                                    additionalData.stateData = JSON.stringify(
                                        stateData);

                                    if (selectedAlternativePaymentMethodType() ==
                                        'ratepay') {
                                        additionalData.df_value = innerSelf.getRatePayDeviceIdentToken();
                                    }

                                    data.additional_data = additionalData;

                                    self.placeRedirectOrder(data,
                                        innerSelf.component);
                                }

                                return false;
                            },
                            getRatePayDeviceIdentToken: function() {
                                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
                            },
                            getCode: function() {
                                return self.getCode();
                            }
                        };

                        accumulator.push(result);
                        return accumulator;
                    }, []);

                return paymentList;
            },
            placeRedirectOrder: function(data, component) {
                var self = this;

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
                                    orderId, component);
                            });
                    },
                );
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
            selectPaymentMethodType: function() {
                var self = this;

                // set payment method to adyen_hpp
                var data = {
                    'method': self.method,
                    'po_number': null,
                    'additional_data': {
                        brand_code: self.paymentMethod.type,
                    },
                };

                // set the payment method type
                selectedAlternativePaymentMethodType(self.paymentMethod.type);

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
            getSelectedAlternativePaymentMethodType: ko.computed(function() {

                if (!quote.paymentMethod()) {
                    return null;
                }

                if (quote.paymentMethod().method == paymentMethod()) {
                    return selectedAlternativePaymentMethodType();
                }
                return null;
            }),
            /**
             * Based on the response we can start a action component or redirect
             * @param responseJSON
             */
            validateActionOrPlaceOrder: function(
                responseJSON, orderId, component) {
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
                    self.renderActionComponent(response.resultCode,
                        response.action, component);
                }
            },
            renderActionComponent: function(resultCode, action, component) {
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

                // If this is a handleAction method then do it that way, otherwise createFrom action
                if (self.handleActionPaymentMethods.includes(
                    selectedAlternativePaymentMethodType())) {
                    self.actionComponent = component.handleAction(action);
                } else {
                    if (resultCode !== 'RedirectShopper') {
                        self.popupModal.modal('openModal');
                    }

                    self.actionComponent = self.checkoutComponent.createFromAction(action).
                        mount(actionNode);
                }
            },
            handleOnSubmit: function(state, component) {
                if (this.validate()) {
                    var data = {};
                    data.method = this.getCode();

                    var additionalData = {};
                    additionalData.brand_code = selectedAlternativePaymentMethodType();

                    let stateData = component.data;

                    additionalData.stateData = JSON.stringify(stateData);

                    if (selectedAlternativePaymentMethodType() == 'ratepay') {
                        additionalData.df_value = this.getRatePayDeviceIdentToken();
                    }

                    data.additional_data = additionalData;
                    this.placeRedirectOrder(data, component);
                }

                return false;

            },
            handleOnCancel: function(state, component) {
                var self = this;

                // call endpoint with state.data if available
                let request = {};
                if (!!state.data) {
                    request = state.data;
                }

                request.orderId = self.orderId;
                request.cancelled = true;

                adyenPaymentService.paymentDetails(request).done(function() {
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl,
                    );
                }).fail(function(response) {
                    fullScreenLoader.stopLoader();
                    if (self.popupModal) {
                        self.closeModal(self.popupModal);
                    }
                    errorProcessor.process(response,
                        self.currentMessageContainer);
                    self.isPlaceOrderActionAllowed(true);
                    self.showErrorMessage(response);
                });
            },
            handleOnAdditionalDetails: function(state, component) {
                var self = this;

                // call endpoint with state.data if available
                let request = {};
                if (!!state.data) {
                    request = state.data;
                }

                request.orderId = self.orderId;

                adyenPaymentService.paymentDetails(request).done(function() {
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl,
                    );
                }).fail(function(response) {
                    fullScreenLoader.stopLoader();
                    if (self.popupModal) {
                        self.closeModal(self.popupModal);
                    }
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
                    $('#messages-' + selectedAlternativePaymentMethodType()).
                        text((response['responseJSON'].message).replace('%1',
                            response['responseJSON'].parameters[0])).
                        slideDown();
                } else {
                    $('#messages-' + selectedAlternativePaymentMethodType()).
                        text(response['responseJSON'].message).
                        slideDown();
                }

                setTimeout(function() {
                    $('#messages-' + selectedAlternativePaymentMethodType()).
                        slideUp();
                }, 10000);
            },
            validate: function() {
                var form = '#payment_form_' + this.getCode() + '_' +
                    selectedAlternativePaymentMethodType();

                var validate = $(form).validation() &&
                    $(form).validation('isValid');

                return validate && additionalValidators.validate();
            },
            isButtonActive: function() {
                return this.getCode() == this.isChecked() &&
                    this.isPlaceOrderActionAllowed();
            },
            getRatePayDeviceIdentToken: function() {
                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
            },
            getCode: function() {
                return window.checkoutConfig.payment.adyenHpp.methodCode;
            },
        });
    },
);
