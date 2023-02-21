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
    'uiLayout',
    'mage/url',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Ui/js/model/messages',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/model/adyen-checkout'
], function (
    $,
    ko,
    layout,
    url,
    placeOrderAction,
    fullScreenLoader,
    Messages,
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

        getIcons: function (type) {
            return this.details.icon;
        },

        getClientKey: function () {
            return adyenConfiguration.getClientKey();
        },

        getMessageContainer: function () {
            return messageContainer;
        },

        afterPlaceOrder: function () {
            return self.afterPlaceOrder(); // needed for placeOrder method
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

        // TODO check: isPlaceOrderActionAllowed, installments
        placeOrder: function (data, event) {
            debugger;
            let self = this;

            if (event) {
                event.preventDefault();
            }

            fullScreenLoader.startLoader();
            $.when(placeOrderAction(this.getData())).fail(
                function () {
                    fullScreenLoader.stopLoader();
                    //innerSelf.isPlaceOrderActionAllowed(true);
                },
            ).done(
                function (orderId) {
                    debugger;

                    //self.orderId = orderId;
                    adyenPaymentService.getOrderPaymentStatus(orderId).done(function (responseJSON) {
                        self.handleAdyenResult(responseJSON, orderId);
                    });
                },
            );
        },

        getData: function () {
            const self = this;
            let stateData = self.component.data;
            stateData = JSON.stringify(stateData);
            window.sessionStorage.setItem('adyen.stateData', stateData);
            return {
                method: this.code,
                additional_data: {
                    stateData: stateData,
                    'public_hash': this.publicHash
                },
            };
        },

        handleAdyenResult: function (responseJSON, orderId) {
            debugger;
            let self = this;
            const response = JSON.parse(responseJSON);

            if (!!response.isFinal) {
                // Status is final redirect to the success page
                window.location.replace(url.build(this.successPage));
            } else {
                // Handle action
                self.handleAction(response.action, orderId);
            }
        },
    });
});
