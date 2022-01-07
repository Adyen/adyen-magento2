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
        'Adyen_Payment/js/adyen',
        'Adyen_Payment/js/model/adyen-configuration'
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
        AdyenCheckout,
        adyenConfiguration
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
            loadAdyenPaymentMethods: async function (paymentMethodsResponse) {
                var self = this;

                if (!!paymentMethodsResponse.paymentMethodsResponse) {
                    var paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;
                    this.checkoutComponent = await AdyenCheckout({
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

                var paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;
                var paymentMethodsExtraInfo = paymentMethodsResponse.paymentMethodsExtraDetails;

                var paymentList = _.reduce(paymentMethods,
                    function(accumulator, paymentMethod) {

                        // Some methods belong to a group with brands
                        // Use the brand as identifier
                        const brandMethods = ['giftcard'];
                        if (brandMethods.includes(paymentMethod.type) && !!paymentMethod.brand){
                            paymentMethod.methodGroup = paymentMethod.type;
                            paymentMethod.methodIdentifier = paymentMethod.brand;
                        } else {
                            paymentMethod.methodGroup = paymentMethod.methodIdentifier = paymentMethod.type;
                        }

                        if (!self.isPaymentMethodSupported(
                            paymentMethod.methodGroup)) {
                            return accumulator;
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

                        var result = self.buildPaymentMethodComponentResult(paymentMethod, paymentMethodsExtraInfo);

                        accumulator.push(result);
                        return accumulator;
                    }, []);

                return paymentList;
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
                                if (paymentMethod.methodGroup === paymentMethod.methodIdentifier){
                                    stateData = {
                                        paymentMethod: {
                                            type: selectedAlternativePaymentMethodType(),
                                        },
                                    };
                                } else {
                                    stateData = {
                                        paymentMethod: {
                                            type: paymentMethod.methodGroup,
                                            brand: paymentMethod.methodIdentifier
                                        }
                                    };
                                }

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

                return result;
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
                        if (component.props.methodIdentifier == 'amazonpay') {
                            component.handleDeclineFlow();
                        }
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
                selectedAlternativePaymentMethodType(self.paymentMethod.methodIdentifier);

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
                    // Status is final redirect to the success page
                    $.mage.redirect(
                        window.checkoutConfig.payment[quote.paymentMethod().method].successPage,
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
                var self = this;

                // call endpoint with state.data if available
                let request = {};
                if (!!state.data) {
                    request = state.data;
                }

                request.orderId = self.orderId;

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
            /**
             * Issue with the default currentMessageContainer needs to be resolved for now just throw manually the eror message
             * @param response
             */
            showErrorMessage: function(response) {
                $(".error-message-hpp").show();
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

            buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo, result) {
                var self = this;
                var showPayButton = false;

                if (self.showPayButtonPaymentMethods.includes(
                    paymentMethod.methodGroup)) {
                    showPayButton = true;
                }

                var email = '';
                var shopperGender = '';
                var shopperDateOfBirth = '';

                if (!!customerData.email) {
                    email = customerData.email;
                } else if (!!quote.guestEmail) {
                    email = quote.guestEmail;
                }

                shopperGender = customerData.gender;
                shopperDateOfBirth = customerData.dob;

                var formattedShippingAddress = {};
                var formattedBillingAddress = {};

                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (!!quote.billingAddress()) {
                    formattedBillingAddress = self.getFormattedAddress(quote.billingAddress());
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
                        countryCode: formattedShippingAddress.country ? formattedShippingAddress.country : formattedBillingAddress.country, // Use shipping address details as default and fall back to billing address if missing
                        hasHolderName: adyenConfiguration.getHasHolderName(),
                        holderNameRequired: adyenConfiguration.getHasHolderName() &&
                            adyenConfiguration.getHolderNameRequired(),
                        data: {
                            personalDetails: {
                                firstName: formattedBillingAddress.firstName,
                                lastName: formattedBillingAddress.lastName,
                                telephoneNumber: formattedBillingAddress.telephone,
                                shopperEmail: email,
                                gender: getAdyenGender(shopperGender),
                                dateOfBirth: shopperDateOfBirth,
                            },
                            billingAddress: {
                                city: formattedBillingAddress.city,
                                country: formattedBillingAddress.country,
                                houseNumberOrName: formattedBillingAddress.houseNumber,
                                postalCode: formattedBillingAddress.postalCode,
                                street: formattedBillingAddress.street,
                            },
                        },
                        onChange: function(state) {
                            result.isPlaceOrderAllowed(state.isValid);
                        },
                        onClick: function(resolve, reject) {
                            // for paypal add a workaround, remove when component fixes it
                            if (selectedAlternativePaymentMethodType() === 'paypal') {
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

                if (formattedShippingAddress) {
                    configuration.data.shippingAddress = {
                        city: formattedShippingAddress.city,
                        country: formattedShippingAddress.country,
                        houseNumberOrName: formattedShippingAddress.houseNumber,
                        postalCode: formattedShippingAddress.postalCode,
                        street: formattedShippingAddress.street
                    };
                }

                // Use extra configuration from the paymentMethodsExtraInfo object if available
                if (paymentMethod.methodIdentifier in paymentMethodsExtraInfo && 'configuration' in paymentMethodsExtraInfo[paymentMethod.methodIdentifier]) {
                    configuration = Object.assign(configuration, paymentMethodsExtraInfo[paymentMethod.methodIdentifier].configuration);
                }

                // Extra apple pay configuration
                if (paymentMethod.methodIdentifier.includes('applepay')) {
                    if ('configuration' in configuration &&
                        'merchantName' in configuration.configuration) {
                        configuration.totalPriceLabel = configuration.configuration.merchantName;
                    }
                }
                // Extra amazon pay configuration first call to amazon page
                if (paymentMethod.methodIdentifier.includes('amazonpay')) {
                    configuration.productType = 'PayAndShip';
                    configuration.checkoutMode = 'ProcessOrder';
                    configuration.returnUrl = location.href;

                    if (formattedShippingAddress &&
                        formattedShippingAddress.telephone) {
                        configuration.addressDetails = {
                            name: formattedShippingAddress.firstName +
                                ' ' +
                                formattedShippingAddress.lastName,
                            addressLine1: formattedShippingAddress.street,
                            addressLine2: formattedShippingAddress.houseNumber,
                            city: formattedShippingAddress.city,
                            postalCode: formattedShippingAddress.postalCode,
                            countryCode: formattedShippingAddress.country,
                            phoneNumber: formattedShippingAddress.telephone
                        };
                    }
                }

                return configuration;
            },
            mountPaymentMethodComponent(paymentMethod, configuration, result)
            {
                var self = this;
                try {
                    const containerId = '#adyen-alternative-payment-container-' +
                        paymentMethod.methodIdentifier;
                    var url = new URL(location.href);
                    //Handles the redirect back to checkout page with amazonSessionKey in url
                    if (
                        paymentMethod.methodIdentifier === 'amazonpay'
                        && url.searchParams.has(amazonSessionKey)
                    ) {
                        const amazonPayComponent = self.checkoutComponent.create('amazonpay', {
                            amazonCheckoutSessionId: url.searchParams.get(amazonSessionKey),
                            showOrderButton: false,
                            amount: {
                                currency: configuration.amount.currency,
                                value: configuration.amount.value
                            },
                            returnUrl: location.href,
                            showChangePaymentDetailsButton: false
                        }).mount(containerId);
                        amazonPayComponent.submit();
                        result.component = amazonPayComponent;
                    } else {
                        const component = self.checkoutComponent.create(
                            paymentMethod.methodIdentifier, configuration);
                        if ('isAvailable' in component) {
                            component.isAvailable().then(() => {
                                component.mount(containerId);
                            }).catch(e => {
                                result.isAvailable(false);
                            });
                        } else {
                            component.mount(containerId);
                        }
                        result.component = component;
                    }
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
