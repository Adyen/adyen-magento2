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
            console.log('Initializing adyen-pm-method');
            let self = this;
            this._super();

            let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
            paymentMethodsObserver.subscribe(
                function(paymentMethods) {
                    console.log('Payment methods updated: ', paymentMethods);
                    self.paymentMethodReady(paymentMethods);
                }
            );

            this.paymentMethodReady(paymentMethodsObserver());
        },

        selectPaymentMethod: function () {
            if (!!this.paymentComponent && this.paymentComponent.isValid) {
                $('#stateData').val(JSON.stringify(this.paymentComponent.data));
            }

            return this._super();
        },

        buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo) {
            console.log('Building component configuration for: ', paymentMethod);
            let config = configHelper.buildMultishippingComponentConfiguration(paymentMethod, paymentMethodsExtraInfo);
            console.log('Component configuration: ', config);
            return config;
        }
    });
});
