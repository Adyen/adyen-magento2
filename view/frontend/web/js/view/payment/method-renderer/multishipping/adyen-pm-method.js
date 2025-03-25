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
    'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
    'Adyen_Payment/js/helper/configHelper',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Magento_Checkout/js/model/full-screen-loader'
], function (
    $,
    ko,
    Component,
    configHelper,
    adyenPaymentService,
    fullScreenLoader
) {
    'use strict';

    return Component.extend({
        paymentMethodReady: ko.observable(false),
        isTemplateRendered: ko.observable(false),
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/pm-form'
        },

        enablePaymentMethod: function (paymentMethodsResponse) {
            this._super();
            this.paymentMethodReady(paymentMethodsResponse);
        },

        selectPaymentMethod: function () {
            fullScreenLoader.startLoader();
            let self = this;

            // Only try to mount component if HTML template is rendered.
            this.isTemplateRendered.subscribe(function (response) {
                self.initializeMultishippingPaymentMethod();
            });
            if (this.isTemplateRendered()) {
                self.initializeMultishippingPaymentMethod();
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

            /*
             * Wait until payment component is created and mounted.
             * Then, handle the promise, fetch the stateData and fill out the hidden input field.
             */
            this.createMultishippingCheckoutComponent().then(function (status) {
                if (status) {
                    let paymentComponent = self.getPaymentMethodComponent();

                    if (paymentComponent && paymentComponent.isValid) {
                        $('#stateData').val(JSON.stringify(paymentComponent.data));
                    }

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

        buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo) {
            return configHelper.buildMultishippingComponentConfiguration(paymentMethod, paymentMethodsExtraInfo);
        },

        // Observable is set to true after div element in `pm-form.html` template is rendered
        setIsTemplateRendered: function () {
            this.isTemplateRendered(true);
        }
    });
});
