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
    'Adyen_Payment/js/view/payment/method-renderer/adyen-cc-method'
], function (
    $,
    ko,
    Component
) {
    'use strict';
    return Component.extend({
        paymentMethodReady: ko.observable(false),
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/cc-form'
        },

        enablePaymentMethod: async function (paymentMethodsResponse) {
            this.paymentMethodReady(paymentMethodsResponse);
        },

        buildComponentConfiguration: function () {
            let self = this;
            let baseComponentConfiguration = this._super();

            baseComponentConfiguration.onChange = function (state) {
                $('#stateData').val(state.isValid ? JSON.stringify(state.data) : '');
                self.placeOrderAllowed(!!state.isValid);
                self.storeCc = !!state.data.storePaymentMethod;
            };

            return baseComponentConfiguration
        }
    });
});
