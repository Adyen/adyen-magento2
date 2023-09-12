/**
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
        'underscore',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Payment/js/view/payment/cc-form',
        'Adyen_Payment/js/model/adyen-payment-service',
    ],
    function (
        _,
        $,
        quote,
        Component,
        adyenPaymentService
    ) {
        'use strict';
        let billingAddress = quote.billingAddress();
        let firstname = '';
        let lastname = '';
        if (!!billingAddress) {
            firstname = billingAddress.firstname;
            lastname = billingAddress.lastname;
        }
        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/boleto-form',
                firstname: self.firstname,
                lastname: self.lastname
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'socialSecurityNumber',
                        'boletoType',
                        'firstname',
                        'lastname',
                        'adyenBoletoMethod'
                    ]);
                return this;
            },
            initialize: function () {
                this._super();
                let self = this;

                let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
                paymentMethodsObserver.subscribe(
                    function (paymentMethodsResponse) {
                        // Check the paymentMethods response to enable Boleto payments
                        if (!paymentMethodsResponse.paymentMethodsResponse.paymentMethods.find(self.isBoletoPaymentsEnabled)) {
                            return;
                        } else {
                            self.adyenBoletoMethod(true);
                        }
                    });
            },
            isBoletoPaymentsEnabled: function (paymentMethod) {
                return paymentMethod.type.includes("boleto");
            },
            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getCode: function () {
                return 'adyen_boleto';
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'social_security_number': this.socialSecurityNumber(),
                        'boleto_type': this.boletoType(),
                        'firstname': this.firstname(),
                        'lastname': this.lastname()
                    }
                };
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
            context: function () {
                return this;
            },
            validate: function () {
                let form = 'form[data-role=adyen-boleto-form]';

                let validate = $(form).validation() && $(form).validation('isValid');

                if (!validate) {
                    return false;
                }

                return true;
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            getBoletoTypes: function () {
                return _.map(window.checkoutConfig.payment.adyenBoleto.boletoTypes, function (value, key) {
                    return {
                        'key': value.value,
                        'value': value.label
                    }
                });
            }
        });
    }
);
