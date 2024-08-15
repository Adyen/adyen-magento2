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
    'Adyen_Payment/js/model/adyen-payment-service'
], function (
    $,
    ko,
    Component,
    configHelper,
    adyenPaymentService
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/pm-form'
        },

        paymentMethodReady: ko.observable(false),

        initialize: function() {
            let self = this;
            this._super();

            this.isChecked = ko.observable(false);

            let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
            paymentMethodsObserver.subscribe(
                function(paymentMethods) {
                    self.paymentMethodReady(paymentMethods);
                    self.renderCheckoutComponent();
                }
            );

            this.paymentMethodReady(paymentMethodsObserver());
        },

        selectPaymentMethod: function () {
            let result = this._super();

            // Trigger the payment method change
            this.isChecked(true);

            // Render the checkout component
            this.renderCheckoutComponent();

            if (!!this.paymentComponent && this.paymentComponent.isValid) {
                $('#stateData').val(JSON.stringify(this.paymentComponent.data));
            } else {
                console.warn('Payment component is not valid or not available');
            }

            return result;
        },

        buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo) {
            return configHelper.buildMultishippingComponentConfiguration(paymentMethod, paymentMethodsExtraInfo);
        },

        renderCheckoutComponent: function() {
            let methodCode = this.getMethodCode();
            let paymentMethod = this.paymentMethod();

            if (!paymentMethod || !this.isChecked()) {
                console.error('Payment method is undefined for ', methodCode);
                return;
            }

            let configuration = this.buildComponentConfiguration(paymentMethod, this.paymentMethodsExtraInfo());

            if (this.paymentComponent) {
                this.paymentComponent.update(configuration);
            } else {
                this.mountPaymentMethodComponent(paymentMethod, configuration, methodCode);
            }
        },

        mountPaymentMethodComponent: function(paymentMethod, configuration, methodCode) {
            let self = this;

            const containerId = '#' + paymentMethod.type + 'Container';

            if ($(containerId).length) {
                if (this.paymentComponent && typeof this.paymentComponent.unmount === 'function') {
                    this.paymentComponent.unmount();
                }

                const paymentMethodComponent = this.checkoutComponent.create(
                    paymentMethod.type,
                    configuration
                );


                paymentMethodComponent.mount(containerId);

                this.paymentComponent = paymentMethodComponent;

                if (typeof paymentMethodComponent.onChange === 'function') {
                    paymentMethodComponent.onChange(function(state) {
                        self.onPaymentMethodChange(state, methodCode);
                    });
                } else {
                    console.warn('Unable to add onChange event listener to payment component', paymentMethodComponent);
                }
            } else {
                console.warn('Container not found for', containerId);
            }
        },

        onPaymentMethodChange: function(state, methodCode) {
            if (methodCode !== this.getMethodCode()) {
                return;
            }
            this.isPlaceOrderAllowed(state.isValid);
            $('#stateData').val(state.data ? JSON.stringify(state.data) : '');
        }
    });
});
