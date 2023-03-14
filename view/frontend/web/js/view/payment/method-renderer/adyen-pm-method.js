
/**
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
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/model/adyen-payment-modal',
        'Adyen_Payment/js/model/adyen-checkout'
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
        adyenConfiguration,
        adyenPaymentModal,
        adyenCheckout
    ) {
        'use strict';

        // Excluded from the alternative payment methods rendering process
        var unsupportedPaymentMethods = [
            'scheme',
            'boleto',
            'wechatpay',
            'ratepay'
        ];

        var popupModal;
        var selectedAlternativePaymentMethodType = ko.observable(null);
        var paymentMethod = ko.observable(null);
        const amazonSessionKey = 'amazonCheckoutSessionId';

        return Component.extend({
            self: this,
            isPlaceOrderActionAllowed: ko.observable(quote.billingAddress() != null),
            defaults: {
                template: 'Adyen_Payment/payment/hpp-form',
                orderId: 0,
                paymentMethods: {},
                handleActionPaymentMethods: ['paypal'],
                modalLabel: 'hpp_actionModal'
            },
            showPayButtonPaymentMethods: [
                'paypal',
                'applepay',
                'paywithgoogle',
                'googlepay',
                'amazonpay'
            ],
            initObservable: function() {
                this._super().observe([
                    'selectedAlternativePaymentMethodType',
                    'paymentMethod',
                    'adyenPaymentMethod',
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
            loadAdyenPaymentMethods: async function (paymentMethodsResponse) {
                var self = this;

                this.checkoutComponent = await adyenCheckout.buildCheckoutComponent(
                    paymentMethodsResponse,
                    this.handleOnAdditionalDetails.bind(this),
                    this.handleOnCancel.bind(this),
                    this.handleOnSubmit.bind(this)
                )

                if (!!paymentMethodsResponse.paymentMethodsResponse) {
                    var paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;
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

                    self.adyenPaymentMethod(self.createAdyenPaymentMethod(paymentMethodsResponse))
                }
                fullScreenLoader.stopLoader();
            },
            createAdyenPaymentMethod: function(paymentMethodsResponse) {
                let self = this;

                const paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;
                const paymentMethodsExtraInfo = paymentMethodsResponse.paymentMethodsExtraDetails;
                const paymentMethod = adyenPaymentService.getPaymentMethodFromResponse(self.getTxVariant(), paymentMethods);

                if (paymentMethod) {
                    // Some methods belong to a group with brands
                    // Use the brand as identifier
                    const brandMethods = ['giftcard'];
                    if (brandMethods.includes(paymentMethod.type) && !!paymentMethod.brand){
                        paymentMethod.methodGroup = paymentMethod.type;
                        paymentMethod.methodIdentifier = paymentMethod.brand;
                    } else {
                        paymentMethod.methodGroup = paymentMethod.methodIdentifier = paymentMethod.type;
                    }

                    var messageContainer = new Messages();
                    var name = 'messages-' + paymentMethod.methodIdentifier;
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

                    return self.buildPaymentMethodComponentResult(paymentMethod, paymentMethodsExtraInfo);
                }
            },
            buildPaymentMethodComponentResult: function (paymentMethod, paymentMethodsExtraInfo) {
                var self = this;
                var result = {
                    isAvailable: ko.observable(true),
                    paymentMethod: paymentMethod,
                    method: self.item.method,
                    item: {
                        'title': paymentMethod.name,
                        'method': paymentMethod.methodIdentifier
                    },
                    /**
                     * Observable to enable and disable place order buttons for payment methods
                     * Default value is true to be able to send the real hpp requests that doesn't require any input
                     * @type {observable}
                     */
                    placeOrderAllowed: ko.observable(true),
                    icon: !!paymentMethodsExtraInfo[paymentMethod.methodIdentifier]
                        ? paymentMethodsExtraInfo[paymentMethod.methodIdentifier].icon
                        : {},
                    getMessageName: function() {
                        return 'messages-' + paymentMethod.methodIdentifier;
                    },
                    getMessageContainer: function() {
                        return messageContainer;
                    },
                    validate: function() {
                        return self.validate(paymentMethod.methodIdentifier);
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
                        if (self.showPayButtonPaymentMethods.includes(
                            paymentMethod.methodGroup)) {
                            return false;
                        }

                        return true;
                    },
                    renderCheckoutComponent: function() {
                        result.isPlaceOrderAllowed(false);

                        var configuration = self.buildComponentConfiguration(paymentMethod, paymentMethodsExtraInfo, result);

                        self.mountPaymentMethodComponent(paymentMethod, configuration, result);
                    },

                    placeOrder: function() {
                        debugger;
                        if (result.component) {

                            result.component.showValidation();
                            if (result.component.state.isValid === false) {
                                return false;
                            }
                        }

                        if (this.validate()) {
                            let data = {
                                'method': this.item.method
                            };

                            let additionalData = {};
                            additionalData.brand_code = paymentMethod.methodIdentifier;

                            let stateData;
                            if (result.component) {
                                stateData = result.component.data;
                            } else {
                                // if (paymentMethod.methodGroup === paymentMethod.methodIdentifier){
                                //     stateData = {
                                //         paymentMethod: {
                                //             type: selectedAlternativePaymentMethodType(),
                                //         },
                                //     };
                                // }
                                // else {
                                    stateData = {
                                        paymentMethod: {
                                            type: paymentMethod.methodGroup,
                                            brand: paymentMethod.methodIdentifier
                                        // }
                                    }
                                }
                            };
                            console.log('attempt 2 for', stateData);
                            if (this.getTxVariant() == 'ratepay') {
                                additionalData.df_value = this.getRatePayDeviceIdentToken();
                            }

                            additionalData.stateData = JSON.stringify(stateData);
                            data.additional_data = additionalData;
                            self.placeRedirectOrder(data, result.component);
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

                return result;
            },
            placeRedirectOrder: async function(data, component) {
                const self = this;

                // Place Order but use our own redirect url after
                fullScreenLoader.startLoader();
                $('.hpp-message').slideUp();
                self.isPlaceOrderActionAllowed(false);

                await $.when(placeOrderAction(data, self.currentMessageContainer)).fail(
                    function(response) {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        component.handleReject(response);
                    }
                ).done(
                    function(orderId) {
                        self.afterPlaceOrder();
                        adyenPaymentService.getOrderPaymentStatus(orderId).done(function(responseJSON) {
                            self.validateActionOrPlaceOrder(responseJSON, orderId, component);
                        });
                    }
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
                selectedAlternativePaymentMethodType(self.paymentMethod.methodIdentifier);

                // set payment method
                paymentMethod(self.method);
                console.log(paymentMethod);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },
            /**
             * This method is a workaround to close the modal in the right way and reconstruct the ActionModal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function(popupModal) {
                adyenPaymentModal.closeModal(popupModal, this.modalLabel)
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
            validateActionOrPlaceOrder: function(responseJSON, orderId, component) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the success page
                    $.mage.redirect(
                        window.checkoutConfig.payment.adyen.successPage
                    );
                } else {
                    // render component
                    self.orderId = orderId;
                    self.renderActionComponent(response.resultCode, response.action, component);
                }
            },
            renderActionComponent: function(resultCode, action, component) {
                var self = this;
                var actionNode = document.getElementById(this.modalLabel + 'Content');
                fullScreenLoader.stopLoader();

                // If this is a handleAction method then do it that way, otherwise createFrom action
                if (self.handleActionPaymentMethods.includes(this.getTxVariant())) {
                    self.actionComponent = component.handleAction(action);
                } else {
                    if (resultCode !== 'RedirectShopper') {
                        self.popupModal = adyenPaymentModal.showModal(adyenPaymentService, fullScreenLoader, this.messageContainer, this.orderId, this.modalLabel, this.isPlaceOrderActionAllowed)
                        $("." + this.modalLabel + " .action-close").hide();
                    }
                    self.actionComponent = self.checkoutComponent.createFromAction(action).mount(actionNode);
                }
            },
            handleOnSubmit: async function(state, component) {
                if (this.validate()) {
                    var data = {};
                    data.method = this.getCode();

                    var additionalData = {};
                    additionalData.brand_code = this.getTxVariant();

                    let stateData = component.data;

                    additionalData.stateData = JSON.stringify(stateData);

                    if (selectedAlternativePaymentMethodType() == 'ratepay') {
                        additionalData.df_value = this.getRatePayDeviceIdentToken();
                    }

                    data.additional_data = additionalData;
                    await this.placeRedirectOrder(data, component);
                }

                return false;

            },
            handleOnCancel: function(state, component) {
                const self = this;

                // call endpoint with state.data if available
                let request = {};
                if (!!state.data) {
                    request = state.data;
                }

                request.orderId = self.orderId;
                request.cancelled = true;

                adyenPaymentService.paymentDetails(request).done(function() {
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].successPage,
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
                const self = this;
                // call endpoint with state.data if available
                let request = {};
                if (!!state.data) {
                    request = state.data;
                }

                request.orderId = self.orderId;

                adyenPaymentService.paymentDetails(request).done(function() {
                    $.mage.redirect(
                        window.checkoutConfig.payment.adyen.successPage,
                    );
                }).fail(function(response) {
                    fullScreenLoader.stopLoader();
                    if (self.popupModal) {
                        self.closeModal(self.popupModal);
                    }
                    errorProcessor.process(response,
                        self.currentMessageContainer);
                    self.isPlaceOrderActionAllowed(true);
                });
            },
            validate: function() {
                const form = 'adyen-' + this.getTxVariant() + '-form';
                const validate = $(form).validation() && $(form).validation('isValid');
                return validate && additionalValidators.validate();
            },
            isButtonActive: function() {
                return this.isPlaceOrderActionAllowed();
            },
            getRatePayDeviceIdentToken: function() {
                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
            },
            getTxVariant: function () {
                return window.checkoutConfig.payment.adyen.txVariants[this.item.method];
            },

            /**
             * @param address
             * @returns {{country: (string|*), firstName: (string|*), lastName: (string|*), city: (*|string), street: *, postalCode: (*|string), houseNumber: string, telephone: (string|*)}}
             */
            getFormattedAddress: function(address) {
                function getStreetAndHouseNumberFromAddress(address, houseNumberStreetLine, customerStreetLinesEnabled) {
                    let street = address.street.slice(0, customerStreetLinesEnabled);
                    let drawHouseNumberWithRegex = parseInt(houseNumberStreetLine) === 0 || // Config is disabled
                        houseNumberStreetLine > customerStreetLinesEnabled || // Not enough street lines enabled
                        houseNumberStreetLine > street.length; // House number field is empty

                    let addressArray;
                    if (drawHouseNumberWithRegex) {
                        addressArray = getStreetAndHouseNumberWithRegex(street.join(' ').trim());
                    } else {
                        let houseNumber = street.splice(houseNumberStreetLine - 1, 1);
                        addressArray = {
                            streetName: street.join(' ').trim(),
                            houseNumber: houseNumber.join(' ').trim()
                        }
                    }
                    return addressArray;
                }

                function getStreetAndHouseNumberWithRegex(addressString) {
                    // Match addresses where the street name comes first, e.g. John-Paul's Ave. 1 B
                    let streetFirstRegex = /(?<streetName>[a-zA-Z0-9.'\- ]+)\s+(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)$/;
                    // Match addresses where the house number comes first, e.g. 10 D John-Paul's Ave.
                    let numberFirstRegex = /^(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)\s+(?<streetName>[a-zA-Z0-9.'\- ]+)/;

                    let streetFirstAddress = addressString.match(streetFirstRegex);
                    let numberFirstAddress = addressString.match(numberFirstRegex);

                    if (streetFirstAddress) {
                        return streetFirstAddress.groups;
                    } else if (numberFirstAddress) {
                        return numberFirstAddress.groups;
                    }

                    return {
                        streetName: addressString,
                        houseNumber: 'N/A'
                    };
                }

                let street = getStreetAndHouseNumberFromAddress(
                    address,
                    adyenConfiguration.getHouseNumberStreetLine(),
                    adyenConfiguration.getCustomerStreetLinesEnabled()
                );

                return {
                    city: address.city,
                    country: address.countryId,
                    postalCode: address.postcode,
                    street: street.streetName,
                    houseNumber: street.houseNumber,
                    firstName: address.firstname,
                    lastName: address.lastname,
                    telephone: address.telephone
                };
            },

            mountPaymentMethodComponent(paymentMethod, configuration, result)
            {
                var self = this;
                try {
                    const containerId = '#' + paymentMethod.methodIdentifier + 'Container';
                    var url = new URL(location.href);
                    //Handles the redirect back to checkout page with amazonSessionKey in url
                    if (
                        paymentMethod.methodIdentifier === 'amazonpay'
                        && url.searchParams.has(amazonSessionKey)
                    ) {
                        let componentConfig = {
                            amazonCheckoutSessionId: url.searchParams.get(amazonSessionKey),
                            showOrderButton: false,
                            amount: {
                                currency: configuration.amount.currency,
                                value: configuration.amount.value
                            },
                            showChangePaymentDetailsButton: false
                        }

                        const amazonPayComponent = adyenCheckout.mountPaymentMethodComponent(
                            self.checkoutComponent,
                            'amazonpay',
                            componentConfig,
                            containerId,
                            result
                        )
                        amazonPayComponent.submit();
                        result.component = amazonPayComponent;
                    }

                    result.component = adyenCheckout.mountPaymentMethodComponent(
                        self.checkoutComponent,
                        self.getTxVariant(),
                        configuration,
                        containerId,
                        result
                    );

                } catch (err) {
                    // The component does not exist yet
                    if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                        console.log(err);
                    }
                }
            }
        });
    },
);
