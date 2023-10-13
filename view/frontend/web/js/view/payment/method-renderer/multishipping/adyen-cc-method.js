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
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Magento_Checkout/js/model/full-screen-loader',
], function (
    $,
    ko,
    Component,
    adyenConfiguration,
    adyenPaymentService,
    fullScreenLoader
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/cc-form'
        },

        paymentMethodReady: ko.observable(false),

        initialize: function() {
            let self = this;
            this._super();

            let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
            paymentMethodsObserver.subscribe(
                function(paymentMethods) {
                    self.paymentMethodReady(paymentMethods);
                }
            );

            this.paymentMethodReady(paymentMethodsObserver());
        },

        selectPaymentMethod: function () {
            this.renderSecureFields();
            return this._super();
        },

        renderSecureFields: function () {
            let self = this;

            if (!self.getClientKey) {
                return;
            }
            self.cardComponent = self.checkoutComponent.create('card', {
                enableStoreDetails: self.getEnableStoreDetails(),
                brands: self.getBrands(),
                hasHolderName: adyenConfiguration.getHasHolderName(),
                holderNameRequired: adyenConfiguration.getHasHolderName() &&
                  adyenConfiguration.getHolderNameRequired(),
                onChange: function (state) {
                    $('#stateData').val(state.isValid ? JSON.stringify(state.data) : '');
                    self.placeOrderAllowed(!!state.isValid);
                    self.storeCc = !!state.data.storePaymentMethod;
                }
            }).mount('#cardContainer');
        }
    });
});
