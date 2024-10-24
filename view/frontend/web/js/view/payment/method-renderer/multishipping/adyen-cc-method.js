/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*global define*/
define([
    'jquery',
    'ko',
    'Adyen_Payment/js/view/payment/method-renderer/adyen-cc-method',
    'Magento_Checkout/js/model/full-screen-loader'
], function (
    $,
    ko,
    Component,
    fullScreenLoader
) {
    'use strict';
    return Component.extend({
        paymentMethodReady: ko.observable(false),
        isTemplateRendered: ko.observable(false),
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/cc-form'
        },

        enablePaymentMethod: async function (paymentMethodsResponse) {
            this.paymentMethodReady(paymentMethodsResponse);
        },

        selectPaymentMethod: function () {
            fullScreenLoader.startLoader();
            let self = this;

            this.isTemplateRendered.subscribe(function (response) {
                self.initializeMultishippingPaymentMethod();
            })
            if (this.isTemplateRendered()) {
                this.initializeMultishippingPaymentMethod();
            }

            return true;
        },

        // This will return a promise once the payment component is created and mounted.
        createMultishippingCheckoutComponent: async function () {
            await this.createCheckoutComponent();
            return true;
        },

        initializeMultishippingPaymentMethod: function () {
            let self = this;

            this.createMultishippingCheckoutComponent().then(function (status) {
                if (status) {
                    let paymentComponent = self.getPaymentMethodComponent();

                    // Remove previously assigned event listeners
                    $("#payment-continue").off();
                    // Assign event listener for component validation
                    $("#payment-continue").on("click", function () {
                        paymentComponent.showValidation();
                    });
                } else {
                    console.warn('Payment component could not be generated!');
                }

                fullScreenLoader.stopLoader();
            });
        },

        buildComponentConfiguration: function () {
            let self = this;
            let baseComponentConfiguration = this._super();

            baseComponentConfiguration.onChange = function (state) {
                $('#stateData').val(state.isValid ? JSON.stringify(state.data) : '');
                self.placeOrderAllowed(!!state.isValid);
                self.storeCc = !!state.data.storePaymentMethod;
            };

            return baseComponentConfiguration;
        },

        // Observable is set to true after div element in `cc-form.html` template is rendered
        setIsTemplateRendered: function () {
            this.isTemplateRendered(true);
        }
    });
});
