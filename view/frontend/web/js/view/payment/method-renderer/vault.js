/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'ko',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/model/adyen-checkout'
], function (
    $,
    ko,
    VaultComponent,
    adyenPaymentService,
    adyenConfiguration,
    adyenCheckout
) {
    'use strict';

    let isValidObserver = ko.observable(false);
    let validTokens = {};

    return VaultComponent.extend({
        defaults: {
            template: 'Adyen_Payment/payment/card-vault-form.html',
            checkoutComponentBuilt: false
        },

        /**
         * @returns {exports.initObservable}
         */
        initObservable: function () {
            this._super()
                .observe([
                    'checkoutComponentBuilt'
                ]);

            return this;
        },

        initialize: function () {
            let self = this;
            this._super();
            let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
            paymentMethodsObserver.subscribe(
                function (paymentMethodsResponse) {
                    self.loadCheckoutComponent(paymentMethodsResponse)
                });
            self.loadCheckoutComponent(paymentMethodsObserver());

            return this;
        },

        loadCheckoutComponent: async function(paymentMethodsResponse) {
            this.checkoutComponent = await adyenCheckout.buildCheckoutComponent(
                paymentMethodsResponse,
                this.handleOnAdditionalDetails.bind(this)
            );

            if (this.checkoutComponent) {
                this.checkoutComponentBuilt(true)
            }
        },

        handleOnAdditionalDetails: function (result) {
            var self = this;
            var request = result.data;
            request.orderId = self.orderId;
            debugger;
            console.log('Hello in handleOnAdditionalDetails');
        },

        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        getExpirationMonth: function () {
            return this.getExpirationDate().split('/')[0].padStart(2, '0');
        },

        getExpirationYear: function () {
            return this.getExpirationDate().split('/')[1]
        },

        getCardType: function () {
            return this.details.type;
        },

        getToken: function () {
            return this.publicHash;
        },

        getGatewayToken: function () {
            return this.gatewayToken;
        },

        /**
         * @param {String} type
         * @returns {Boolean}
         */
        getIcons: function (type) {
            return this.details.icon;
        },
        getClientKey: function () {
            return adyenConfiguration.getClientKey();
        },
        renderCardVaultToken: function () {
            let self = this;
            if (!this.getClientKey()) {
                return false
            }

            let componentConfig = {
                hideCVC: false,
                brand: this.getCardType(),
                storedPaymentMethodId: this.getGatewayToken(),
                expiryMonth: this.getExpirationMonth(),
                expiryYear: this.getExpirationYear(),
                //holderName: 'First tester',
                onChange: this.handleOnChange.bind(this)
            }

            self.component = adyenCheckout.mountPaymentMethodComponent(
                this.checkoutComponent,
                'card',
                componentConfig,
                '#cvcContainer-' + this.getId()
            )
            this.component = self.component

            return true
        },
        handleOnChange: function (state, component) {
            validTokens[this.getId()] = !!state.isValid;
            isValidObserver(validTokens)
        },
        isButtonActive: function () {
            return (this.getId() === this.isChecked()) && isValidObserver()[this.getId()];
        },
    });
});
