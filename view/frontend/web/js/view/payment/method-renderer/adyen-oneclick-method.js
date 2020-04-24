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
        'ko',
        'underscore',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/action/redirect-on-success',
        'uiLayout',
        'Magento_Ui/js/model/messages',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Adyen_Payment/js/bundle',
        'Adyen_Payment/js/model/adyen-configuration'
    ],
    function (
        ko,
        _,
        $,
        Component,
        selectPaymentMethodAction,
        additionalValidators,
        quote,
        checkoutData,
        redirectOnSuccessAction,
        layout,
        Messages,
        url,
        fullScreenLoader,
        setPaymentMethodAction,
        urlBuilder,
        storage,
        placeOrderAction,
        errorProcessor,
        adyenPaymentService,
        adyenComponentBundle,
        adyenConfiguration
    ) {
        'use strict';

        var messageComponents;

        var recurringDetailReference = ko.observable(null);
        var variant = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var numberOfInstallments = ko.observable(null);
        var isValid = ko.observable(false);

        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/oneclick-form',
                recurringDetailReference: '',
                variant: '',
                numberOfInstallments: '',
                checkoutComponent: {},
                storedPayments: []
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'recurringDetailReference',
                        'creditCardType',
                        'variant',
                        'numberOfInstallments'
                    ]);
                return this;
            },
            initialize: function () {
                var self = this;
                this._super();

                this.checkoutComponent = adyenPaymentService.getCheckoutComponent();
                this.storedPayments = this.checkoutComponent.paymentMethodsResponse.storedPaymentMethods;

                // create component needs to be in initialize method
                var messageComponents = {};
                _.map(this.storedPayments, function (storedPayment) {

                    var messageContainer = new Messages();
                    var name = 'messages-' + storedPayment.id;
                    var messagesComponent = {
                        parent: self.name,
                        name: 'messages-' + storedPayment.id,
                        // name: self.name + '.messages',
                        displayArea: 'messages-' + storedPayment.id,
                        component: 'Magento_Ui/js/view/messages',
                        config: {
                            messageContainer: messageContainer
                        }
                    };
                    layout([messagesComponent]);

                    messageComponents[name] = messageContainer;
                });
                this.messageComponents = messageComponents;
            },
            /**
             * List all Adyen billing agreements
             * Set up installments
             *
             * @returns {Array}
             */
            getAdyenStoredPayments: function () {
                var self = this;

                // convert to list so you can iterate
                var paymentList = _.map(this.storedPayments, function (storedPayment) {
                    var messageContainer = self.messageComponents['messages-' + storedPayment.id];

                    // for recurring enable the placeOrder button at all times
                    var placeOrderAllowed = true;
                    if (self.hasVerification()) {
                        placeOrderAllowed = false;
                    } else {
                        // for recurring cards there is no validation needed
                        isValid(true);
                    }

                    var agreementLabel = storedPayment.name + ', ' + storedPayment.holderName + ', **** ' + storedPayment.lastFour;

                    console.log(self.checkoutComponent);

                    return {
                        'label': agreementLabel,
                        'value': storedPayment.storedPaymentMethodId,
                        'brand': storedPayment.brand,
                        'logo': {},
                        'installment': '',
                        'method': self.item.method,
                        'encryptedCreditCardVerificationNumber': '',
                        'placeOrderAllowed': ko.observable(placeOrderAllowed),
                        checkoutComponent: self.checkoutComponent,
                        isButtonActive: function () {
                            return self.isActive() && this.getCode() == self.isChecked() && self.isBillingAgreementChecked() && this.placeOrderAllowed();
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
                         * Renders the stored payment component,
                         */
                        renderStoredPaymentComponent: function () {
                            var self = this;

                            if (!adyenConfiguration.getOriginKey()) {
                                return;
                            }

                            var hideCVC = false;
                            if (!this.hasVerification()) {
                                hideCVC = true;
                            }

                            /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
                            var configuration = Object.assign(storedPayment, {
                                hideCVC: hideCVC,
                                'onChange': function (state) {
                                    if (!!state.isValid) {
                                        self.stateData = state.data;
                                        self.placeOrderAllowed = true;
                                    } else {
                                        self.placeOrderAllowed = false;
                                        self.stateData = {};
                                    }
                                }
                            });

                            var oneClickCard = self.checkoutComponent
                                .create(storedPayment.type, configuration)
                                .mount('#storedPaymentContainer-' + self.value);
                        },
                        /**
                         * Based on the response we can start a 3DS2 validation or place the order
                         * @param responseJSON
                         */
                        validateThreeDS2OrPlaceOrder: function (responseJSON, orderId) {
                            var self = this;
                            var response = JSON.parse(responseJSON);

                            if (!!response.threeDS2) {
                                // render component
                                self.renderThreeDS2Component(response.type, response.token, orderId);
                            } else {
                                window.location.replace(url.build(window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl));
                            }
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
                        renderThreeDS2Component: function (type, token, orderId) {
                            var self = this;

                            var threeDS2Node = document.getElementById('threeDS2ContainerOneClick');

                            if (type == "IdentifyShopper") {
                                self.threeDS2Component = checkout.create('threeDS2DeviceFingerprint', {
                                    fingerprintToken: token,
                                    onComplete: function (result) {
                                        var request = result.data;
                                        request.orderId = orderId;
                                        adyenPaymentService.processThreeDS2(request).done(function (responseJSON) {
                                            self.validateThreeDS2OrPlaceOrder(responseJSON, orderId)
                                        }).fail(function (result) {
                                            errorProcessor.process(result, self.getMessageContainer());
                                            self.isPlaceOrderActionAllowed(true);
                                            fullScreenLoader.stopLoader();
                                        });
                                    },
                                    onError: function (error) {
                                        console.log(JSON.stringify(error));
                                    }
                                });
                            } else if (type == "ChallengeShopper") {
                                fullScreenLoader.stopLoader();


                                var popupModal = $('#threeDS2ModalOneClick').modal({
                                    // disable user to hide popup
                                    clickableOverlay: false,
                                    responsive: true,
                                    innerScroll: false,
                                    // empty buttons, we don't need that
                                    buttons: [],
                                    modalClass: 'threeDS2Modal'
                                });

                                popupModal.modal("openModal");

                                self.threeDS2Component = checkout
                                    .create('threeDS2Challenge', {
                                        challengeToken: token,
                                        onComplete: function (result) {
                                            popupModal.modal("closeModal");
                                            fullScreenLoader.startLoader();
                                            var request = result.data;
                                            request.orderId = orderId;
                                            adyenPaymentService.processThreeDS2(request).done(function (responseJSON) {
                                                self.validateThreeDS2OrPlaceOrder(responseJSON, orderId)
                                            }).fail(function (result) {
                                                errorProcessor.process(result, self.getMessageContainer());
                                                self.isPlaceOrderActionAllowed(true);
                                                fullScreenLoader.stopLoader();
                                            });
                                        },
                                        onError: function (error) {
                                            console.log(JSON.stringify(error));
                                        }
                                    });
                            }

                            self.threeDS2Component.mount(threeDS2Node);
                        },
                        /**
                         * Builds the payment details part of the payment information reqeust
                         *
                         * @returns {{additional_data: {state_data: string}, method: *}}
                         */
                        getData: function () {
                            var self = this;

                            return {
                                "method": self.method,
                                additional_data: {
                                    'state_data': JSON.stringify(this.stateData),
                                }
                            };
                        },
                        validate: function () {

                            var code = self.item.method;
                            var value = this.value;
                            var codeValue = code + '_' + value;

                            var form = 'form[data-role=' + codeValue + ']';

                            var validate = $(form).validation() && $(form).validation('isValid');

                            // bcmc does not have any cvc
                            if (!validate || (isValid() == false && variant() != "bcmc" && variant() != "maestro")) {
                                return false;
                            }

                            return true;
                        },
                        getCode: function () {
                            return self.item.method;
                        },
                        hasVerification: function () {
                            return self.hasVerification()
                        },
                        getMessageName: function () {
                            return 'messages-' + storedPayment.id;
                        },
                        getMessageContainer: function () {
                            return messageContainer;
                        },
                        getOriginKey: function () {
                            return adyenConfiguration.getOriginKey();
                        },
                        isPlaceOrderActionAllowed: function () {
                            return self.isPlaceOrderActionAllowed(); // needed for placeOrder method
                        },
                        afterPlaceOrder: function () {
                            return self.afterPlaceOrder(); // needed for placeOrder method
                        },
                        getPlaceOrderDeferredObject: function () {
                            return $.when(
                                placeOrderAction(this.getData(), this.getMessageContainer())
                            );
                        },
                    }
                });

                return paymentList;
            },
            /**
             * Select a billing agreement (stored one click payment method) from the list
             *
             * @returns {boolean}
             */
            selectBillingAgreement: function () {
                var self = this;

                // set payment method data
                var data = {
                    "method": self.method,
                    "po_number": null,
                    "additional_data": {
                        recurring_detail_reference: self.value
                    }
                };

                // set the brandCode
                recurringDetailReference(self.value);
                variant(self.brand);

                // set payment method
                paymentMethod(self.method);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },
            isBillingAgreementChecked: ko.computed(function () {

                if (!quote.paymentMethod()) {
                    return null;
                }

                if (quote.paymentMethod().method == paymentMethod()) {
                    return recurringDetailReference();
                }

                return null;
            }),
            placeOrderHandler: null,
            validateHandler: null,
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            getCode: function () {
                return window.checkoutConfig.payment.adyenOneclick.methodCode;
            },
            isActive: function () {
                return true;
            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            context: function () {
                return this;
            },
            //TODO create configuration for this on admin
            isShowLegend: function () {
                return true;
            },
            //TODO create a configuration for information on admin
            getLegend: function () {
                return '';
            },
            hasVerification: function () {
                return window.checkoutConfig.payment.adyenOneclick.hasCustomerInteraction;
            },
        });
    }
);
