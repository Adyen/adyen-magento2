/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/model/adyen-payment-modal',
        'Adyen_Payment/js/model/adyen-checkout'
    ],
    function(
        ko,
        $,
        Component,
        quote,
        additionalValidators,
        adyenPaymentService,
        fullScreenLoader,
        placeOrderAction,
        errorProcessor,
        adyenConfiguration,
        adyenPaymentModal,
        adyenCheckout
    ) {
        'use strict';
        let popupModal;

        return Component.extend({
            self: this,
            isAvailable: ko.observable(true),

            defaults: {
                template: 'Adyen_Payment/payment/pm-form',
                orderId: null,
                modalLabel: 'hpp_actionModal'
            },
            placeOrderButtonVisible: true,
            initObservable: function() {
                this._super().observe([
                    'paymentMethod',
                    'paymentMethodsExtraInfo',
                    'adyenPaymentMethod',
                    'isPlaceOrderActionAllowed',
                    'placeOrderAllowed'
                ]);
                return this;
            },

            initialize: function() {
                this._super();
                let self = this;


                let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
                paymentMethodsObserver.subscribe(
                    function(paymentMethodsResponse) {
                        self.createCheckoutComponent(paymentMethodsResponse);
                    }
                );

                if(!!paymentMethodsObserver()) {
                    self.createCheckoutComponent(paymentMethodsObserver());
                }
            },

            paymentMethodStates: {},

            createCheckoutComponent: async function(paymentMethodsResponse) {
                // Set to null by default and modify depending on the paymentMethods response
                this.adyenPaymentMethod(null);
                if (this.checkBrowserCompatibility() && !!paymentMethodsResponse.paymentMethodsResponse) {
                    this.checkoutComponent = await adyenCheckout.buildCheckoutComponent(
                        paymentMethodsResponse,
                        this.handleOnAdditionalDetails.bind(this),
                        this.handleOnCancel.bind(this),
                        this.handleOnSubmit.bind(this),
                        this.handleOnError.bind(this)
                    );

                    if (!!this.checkoutComponent) {
                        this.paymentMethod(
                            adyenPaymentService.getPaymentMethodFromResponse(
                                this.getTxVariant(),
                                paymentMethodsResponse.paymentMethodsResponse.paymentMethods
                            )
                        );

                        if (!!this.paymentMethod()) {
                            this.paymentMethodsExtraInfo(paymentMethodsResponse.paymentMethodsExtraDetails);
                            // Setting the icon and method txvariant as an accessible field if it is available
                            this.adyenPaymentMethod({
                                icon: !!paymentMethodsResponse.paymentMethodsExtraDetails[this.getTxVariant()]
                                    ? paymentMethodsResponse.paymentMethodsExtraDetails[this.getTxVariant()].icon
                                    : undefined,
                                method: this.getTxVariant()
                            });
                        }
                    }
                }

                fullScreenLoader.stopLoader();
            },

            handleOnSubmit: async function(state, component) {
                if (this.validate()) {
                    let data = {};
                    data.method = this.getCode();

                    let additionalData = {};
                    let stateData = component.data;
                    additionalData.stateData = JSON.stringify(stateData);
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

                request.cancelled = true;

                adyenPaymentService.paymentDetails(request, self.orderId).done(function() {
                    $.mage.redirect(
                        window.checkoutConfig.payment.adyen.successPage
                    );
                }).fail(function(response) {
                    fullScreenLoader.stopLoader();
                    if (self.popupModal) {
                        self.closeModal(self.popupModal);
                    }
                    errorProcessor.process(response,
                        self.currentMessageContainer);
                    self.isPlaceOrderAllowed(true);
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

                adyenPaymentService.paymentDetails(request, self.orderId).done(function() {
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
                    self.isPlaceOrderAllowed(true);
                });
            },

            handleOnError: function (error, component) {
                /*
                 *  Passing false as the response to hide the actual error message from the shopper for security.
                 *  This will show a generic error message instead of the actual error message.
                 */
                this.handleOnFailure(error, component);
            },

            handleOnFailure: function(error, component) {
                this.isPlaceOrderAllowed(true);
                fullScreenLoader.stopLoader();
                errorProcessor.process(error, this.currentMessageContainer);
            },
            
            renderCheckoutComponent: function() {
                let methodCode = this.getMethodCode();

                let configuration = this.buildComponentConfiguration(this.paymentMethod(), this.paymentMethodsExtraInfo());

                this.mountPaymentMethodComponent(this.paymentMethod(), configuration, methodCode);

                setTimeout(() => {
                    this.updatePlaceOrderButtonState(methodCode);
                }, 0);
            },
            updatePlaceOrderButtonState: function(methodCode) {
                let state = this.initializeMethod(methodCode);
                let container = $(`#${methodCode}Container`);

                // Check if the payment method has any input fields
                let hasForm = container.find('input, select, textarea').length > 0;

                if (hasForm) {
                    // If there's a form, start with button disabled and let the onChange handler manage its state
                    state.isPlaceOrderAllowed(false);
                } else {
                    // If there's no form, it's likely a direct redirect method, so enable the button
                    state.isPlaceOrderAllowed(true);
                }
            },

            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let self = this;
                let showPayButton = false;

                let formattedShippingAddress = {};
                let formattedBillingAddress = {};

                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (!!quote.billingAddress()) {
                    formattedBillingAddress = self.getFormattedAddress(quote.billingAddress());
                }
                /* Use the storedPaymentMethod object and the custom onChange function as the configuration object together */
                let configuration = Object.assign(paymentMethod,
                    {
                        showPayButton: showPayButton,
                        countryCode: formattedShippingAddress.country ? formattedShippingAddress.country : formattedBillingAddress.country, // Use shipping address details as default and fall back to billing address if missing
                        data: {},
                        onChange: function (state) {
                            self.isPlaceOrderAllowed(state.isValid);
                        },
                    });

                return configuration;
            },

            getTxVariant: function () {
                return window.checkoutConfig.payment.adyen.txVariants[this.getCode()];
            },

            getMethodCode: function () {
                return this.item.method;
            },

            mountPaymentMethodComponent: function(paymentMethod, configuration, methodCode) {
                let self = this;
                let state = this.initializeMethod(methodCode);

                try {
                    const containerId = '#' + paymentMethod.type + 'Container';

                    state.paymentComponent = adyenCheckout.mountPaymentMethodComponent(
                        self.checkoutComponent,
                        self.getTxVariant(),
                        configuration,
                        containerId
                    );
                } catch (err) {
                    if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                        console.error(err);
                    }
                }
            },

            validate: function() {
                let state = this.initializeMethod(this.getMethodCode());

                if (!state.paymentComponent) {
                    return true;
                }

                state.paymentComponent.showValidation();

                if (!this.isComponentValid(state.paymentComponent)) {
                    return false;
                }

                const form = '#adyen-' + this.getTxVariant() + '-form';
                return $(form).validation() && $(form).validation('isValid') && additionalValidators.validate();
            },

            isComponentValid: function(component) {
                return component.state.isValid !== false &&
                    !this.isPaymentDataEmpty(component.state) &&
                    !this.isPaymentDataEmpty(component.data);
            },

            isPaymentDataEmpty: function(obj) {
                if (obj && obj.data && typeof obj.data === 'object' && Object.keys(obj.data).length > 0) {
                    return Object.values(obj.data).every(value =>
                        value === '' || (typeof value === 'object' && Object.keys(value).length === 0)
                    );
                }
                return false;
            },


            showErrorMessage: function(message) {
                messageList.addErrorMessage({
                    message: message
                });
            },


            initializeMethod: function(methodCode) {
                if (!this.paymentMethodStates[methodCode]) {
                    this.paymentMethodStates[methodCode] = {
                        isPlaceOrderAllowed: ko.observable(true),
                        paymentComponent: null
                    };
                }
                return this.paymentMethodStates[methodCode];
            },

            isPlaceOrderAllowed: function(methodCode) {
                let state = this.initializeMethod(methodCode);
                return state.isPlaceOrderAllowed;
            },

            setPlaceOrderAllowed: function(methodCode, allowed) {
                let state = this.initializeMethod(methodCode);
                state.isPlaceOrderAllowed(allowed);
            },

            showPlaceOrderButton: function() {
                return this.placeOrderButtonVisible;
            },

            placeOrder: function() {
                let methodCode = this.getMethodCode();
                let state = this.initializeMethod(methodCode);

                if (state.paymentComponent) {
                    state.paymentComponent.showValidation();
                }

                if (this.validate()) {
                    let data = {
                        'method': this.item.method
                    };

                    let additionalData = {};
                    additionalData.brand_code = this.paymentMethod().type;
                    additionalData.frontendType = 'luma';

                    let stateData;
                    if (state.paymentComponent) {
                        stateData = state.paymentComponent.data;
                    } else {
                        stateData = {
                            paymentMethod: {
                                type: this.paymentMethod().type,
                            },
                        };
                    }

                    additionalData.stateData = JSON.stringify(stateData);
                    data.additional_data = additionalData;

                    this.placeRedirectOrder(data, state.paymentComponent);
                } else {
                    this.isPlaceOrderAllowed(true);
                }

                return false;
            },

            placeRedirectOrder: async function(data, component) {
                const self = this;

                fullScreenLoader.startLoader();
                $('.hpp-message').slideUp();
                self.isPlaceOrderAllowed(false);

                try {
                    const orderId = await placeOrderAction(data, self.currentMessageContainer);
                    const responseJSON = await adyenPaymentService.getOrderPaymentStatus(orderId);
                    self.validateActionOrPlaceOrder(responseJSON, orderId, component);
                } catch (response) {
                    self.handleOnFailure(response, component);
                }
            },

            /**
             * This method is a workaround to close the modal in the right way and reconstruct the ActionModal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function(popupModal) {
                adyenPaymentModal.closeModal(popupModal, this.modalLabel)
            },

            /**
             * Based on the response we can start a action component or redirect
             * @param responseJSON
             */
            validateActionOrPlaceOrder: function(responseJSON, orderId, component) {
                let self = this;
                let response = JSON.parse(responseJSON);

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
                let self = this;
                let actionNode = document.getElementById(this.modalLabel + 'Content');
                fullScreenLoader.stopLoader();

                if (resultCode !== 'RedirectShopper') {
                    self.popupModal = adyenPaymentModal.showModal(adyenPaymentService, fullScreenLoader, this.messageContainer, this.orderId, this.modalLabel, this.isPlaceOrderActionAllowed)
                    $("." + this.modalLabel + " .action-close").hide();
                }

                self.actionComponent = self.checkoutComponent.createFromAction(action).mount(actionNode);
            },


            isButtonActive: function() {
                let methodCode = this.getMethodCode();
                return this.isPlaceOrderAllowed(methodCode);
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
                    const streetFirstRegex = /^(?<streetName>[a-zA-Z0-9.'\- ]{1,100})\s+(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)$/;

                    // Match addresses where the house number comes first, e.g. 10 D John-Paul's Ave.
                    const numberFirstRegex = /^(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)\s+(?<streetName>[a-zA-Z0-9.'\- ]{1,100})$/;

                    const streetFirstAddress = addressString.match(streetFirstRegex);
                    const numberFirstAddress = addressString.match(numberFirstRegex);

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

            checkBrowserCompatibility: function () {
                return true;
            }
        });
    },
);
