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

            this.selectMultishippingPaymentMethod().then(function (status) {
                if (status) {
                    let paymentComponent = self.getPaymentMethodComponent();

                    if (!!paymentComponent && paymentComponent.isValid) {
                        $('#stateData').val(JSON.stringify(paymentComponent.data));
                    } else {
                        console.warn('Payment component is not valid or not available');
                    }
                } else {
                    console.warn('Payment component could not be generated!');
                }

                fullScreenLoader.stopLoader();
            });

            return true;
        },

        selectMultishippingPaymentMethod: async function () {
            await this.createCheckoutComponent();
            return true;
        },

        buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo) {
            return configHelper.buildMultishippingComponentConfiguration(paymentMethod, paymentMethodsExtraInfo);
        }
    });
});
