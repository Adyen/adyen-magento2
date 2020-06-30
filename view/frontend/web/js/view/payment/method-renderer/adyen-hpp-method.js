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
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/storage',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'uiLayout',
        'Magento_Ui/js/model/messages',
        'Adyen_Payment/js/bundle',
        'Adyen_Payment/js/model/adyen-configuration'
    ],
    function (ko,
              $,
              Component,
              selectPaymentMethodAction,
              quote,
              checkoutData,
              additionalValidators,
              storage,
              adyenPaymentService,
              urlBuilder,
              customer,
              fullScreenLoader,
              placeOrderAction,
              layout,
              Messages,
              AdyenComponent,
              adyenConfiguration) {
        'use strict';
        var brandCode = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var messageComponents;
        var unsupportedPaymentMethods = ['scheme', 'boleto', 'bcmc_mobile_QR', 'wechatpay', /^bcmc$/, "applepay", "paywithgoogle", "paypal"];
        /**
         * Shareble adyen checkout component
         * @type {AdyenCheckout}
         */
        var checkoutComponent;

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/hpp-form',
                brandCode: '',
                stateData: {}
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'brandCode',
                        'paymentListObservable'
                    ]);
                return this;
            }, initialize: function () {

                var self = this;
                this._super();

                var paymentMethodsObservable = adyenPaymentService.getPaymentMethodsObservable();

                /**
                 * Create sherable checkout component
                 * @type {AdyenCheckout}
                 */
                self.checkoutComponent = adyenPaymentService.getCheckoutComponent();
                self.setAdyenHppPaymentMethods();

                paymentMethodsObservable.subscribe(function() {
                    self.checkoutComponent = adyenPaymentService.getCheckoutComponent();
                    self.setAdyenHppPaymentMethods();
                });
            },
            getAdyenHppPaymentMethods: function () {
                return this.paymentListObservable;
            },
            setAdyenHppPaymentMethods: function () {
                var self = this;

                fullScreenLoader.startLoader();

                var paymentMethods = self.checkoutComponent.paymentMethodsResponse.paymentMethods;

                // create component needs to be in initialize method
                var messageComponents = {};
                _.map(paymentMethods, function (value) {

                    var messageContainer = new Messages();
                    var name = 'messages-' + self.getBrandCodeFromPaymentMethod(value);
                    var messagesComponent = {
                        parent: self.name,
                        name: 'messages-' + self.getBrandCodeFromPaymentMethod(value),
                        displayArea: 'messages-' + self.getBrandCodeFromPaymentMethod(value),
                        component: 'Magento_Ui/js/view/messages',
                        config: {
                            messageContainer: messageContainer
                        }
                    };
                    layout([messagesComponent]);

                    messageComponents[name] = messageContainer;
                });

                self.messageComponents = messageComponents;

                // Iterate through the payment methods and render them
                var paymentList = _.reduce(paymentMethods, function (accumulator, paymentMethod) {
                    if (!self.isPaymentMethodSupported(paymentMethod.type)) {
                        return accumulator;
                    }

                    var result = {};

                    /**
                     * Returns the payment method's brand code (in checkout api it is the type)
                     * @returns {*}
                     */
                    result.getBrandCode = function () {
                        return self.getBrandCodeFromPaymentMethod(paymentMethod);
                    };

                    result.brandCode = result.getBrandCode();
                    result.name = paymentMethod.name;
                    result.method = self.item.method;
                    /**
                     * Observable to enable and disable place order buttons for payment methods
                     * Default value is true to be able to send the real hpp requiests that doesn't require any input
                     * @type {observable}
                     */
                    result.placeOrderAllowed = ko.observable(true);
                    result.getCode = function () {
                        return self.item.method;
                    };
                    result.validate = function () {
                        return self.validate(result.getBrandCode());
                    };
                    result.placeRedirectOrder = function placeRedirectOrder(data)
                    {
                        return self.placeRedirectOrder(data);
                    };

                    /**
                     * Set and get if the place order action is allowed
                     * Sets the placeOrderAllowed observable and the original isPlaceOrderActionAllowed as well
                     * @param bool
                     * @returns {*}
                     */
                    result.isPlaceOrderAllowed = function (bool) {
                        self.isPlaceOrderActionAllowed(bool);
                        return result.placeOrderAllowed(bool);
                    };
                    result.afterPlaceOrder = function () {
                        return self.afterPlaceOrder();
                    };
                    /**
                     * Renders the secure fields,
                     * creates the ideal component,
                     * sets up the callbacks for ideal components and
                     */
                    result.renderCheckoutComponent = function () {
                        result.isPlaceOrderAllowed(false);

                        var showPayButton = false;
                        const showPayButtonPaymentMethods = [
                            'paypal'
                        ];

                        if (showPayButtonPaymentMethods.includes(paymentMethod.type)) {
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

                        if (!!quote && !!quote.shippingAddress()) {
                             city = quote.shippingAddress().city;
                             country = quote.shippingAddress().countryId;
                             postalCode = quote.shippingAddress().postcode;
                             street = quote.shippingAddress().street.join(" ");
                        }

                        /*Use the storedPaymentMethod object and the custom onChange function as the configuration object together*/
                        var configuration = {
                            showPayButton: showPayButton,
                            data: {
                                billingAddress: {
                                    city: quote.shippingAddress().city,
                                    country: quote.shippingAddress().countryId,
                                    houseNumberOrName: '',
                                    postalCode: quote.shippingAddress().postcode,
                                    street: quote.shippingAddress().street.join(" ")
                                }
                            },
                            onChange: function (state) {
                                if (!!state.isValid) {
                                    result.stateData = state.data;
                                    result.isPlaceOrderAllowed(true);
                                } else {
                                    result.stateData = {};
                                    result.isPlaceOrderAllowed(false);
                                }
                            }
                        };

                        try {
                            self.checkoutComponent.create(result.getBrandCode(), configuration).mount('#adyen-alternative-payment-container-' + result.getBrandCode());
                        } catch (err) {
                            // The component does not exist yet
                        }
                    };

                    result.getRatePayDeviceIdentToken = function () {
                        return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
                    };

                    accumulator.push(result);
                    return accumulator;
                }, []);

                self.paymentListObservable(paymentList);
                fullScreenLoader.stopLoader();
            },
            // TODO prefill gender in components where it is available
            getGenderTypes: function () {
                return _.map(window.checkoutConfig.payment.adyenHpp.genderTypes, function (value, key) {
                    return {
                        'key': key,
                        'value': value
                    }
                });
            },
            continueToAdyenBrandCode: function () {
                // set payment method to adyen_hpp
                var self = this;

                if (this.validate() && additionalValidators.validate()) {
                    var data = {};
                    data.method = self.method;

                    var additionalData = {};
                    additionalData.brand_code = self.brandCode;
                    additionalData.state_data = JSON.stringify(self.stateData);

                    if (brandCode() == "ratepay") {
                        additionalData.df_value = this.getRatePayDeviceIdentToken();
                    }

                    data.additional_data = additionalData;
                    this.placeRedirectOrder(data);
                }

                return false;
            },


            // DEFAULT FUNCTIONS
            validate: function (brandCode) {
                var form = '#payment_form_' + this.getCode() + '_' + brandCode;
                var validate = $(form).validation() && $(form).validation('isValid');

                if (!validate) {
                    return false;
                }

                return true;
            },
            placeRedirectOrder: function (data) {
                // Place Order but use our own redirect url after
                var self = this;
                fullScreenLoader.startLoader();

                var messageContainer = this.messageContainer;
                if (brandCode()) {
                    messageContainer = self.messageComponents['messages-' + brandCode()];
                }

                $('.hpp-message').slideUp();

                this.isPlaceOrderActionAllowed(false);
                $.when(
                    placeOrderAction(data, messageContainer)
                ).fail(
                    function (response) {
                        fullScreenLoader.stopLoader();
                        if (!!response['responseJSON'].parameters) {
                            $("#messages-" + brandCode()).text((response['responseJSON'].message).replace('%1', response['responseJSON'].parameters[0])).slideDown();
                        } else {
                            $("#messages-" + brandCode()).text(response['responseJSON'].message).slideDown();
                        }

                        setTimeout(function () {
                            $("#messages-" + brandCode()).slideUp();
                        }, 10000);
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                    }
                ).done(
                    function () {
                        self.afterPlaceOrder();
                        $.mage.redirect(
                            window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl
                        );
                    }
                )
            },

            /**
             *
             * @returns {boolean}
             */
            selectPaymentMethodBrandCode: function () {
                var self = this;

                // set payment method to adyen_hpp
                var data = {
                    "method": self.method,
                    "po_number": null,
                    "additional_data": {
                        brand_code: self.brandCode
                    }
                };

                // set the brandCode
                brandCode(self.brandCode);

                // set payment method
                paymentMethod(self.method);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },


            // CONFIGURATIONS
            isIconEnabled: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            getRatePayDeviceIdentToken: function () {
                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
            },

            /**
             *
             */
            isBrandCodeChecked: ko.computed(function () {
                if (!quote.paymentMethod()) {
                    return null;
                }

                if (quote.paymentMethod().method == paymentMethod()) {
                    return brandCode();
                }

                return null;
            }),

            /**
             * Some payment methods we do not want to render as it requires extra implementation
             * or is already implemented in a separate payment method.
             * Using a match as we want to prevent to render all Boleto and most of the WeChat types
             * @param paymentMethod
             * @returns {boolean}
             */
            isPaymentMethodSupported: function (paymentMethod) {
                if (paymentMethod == 'wechatpayWeb') {
                    return true;
                }
                for (var i = 0; i < unsupportedPaymentMethods.length; i++) {
                    var match = paymentMethod.match(unsupportedPaymentMethods[i]);
                    if (match) {
                        return false;
                    }
                }
                return true;
            },
            /**
             * Returns the payment method's brand code using the payment method from the response object
             * (in checkout api it is the type)
             * @returns {*}
             */
            getBrandCodeFromPaymentMethod: function (paymentMethod) {
                if (typeof paymentMethod.type !== 'undefined') {
                    return paymentMethod.type;
                }

                return '';
            }
        });
    }
);
