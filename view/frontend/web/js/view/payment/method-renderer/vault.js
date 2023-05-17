/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
<<<<<<< HEAD
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
=======
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
>>>>>>> origin/develop
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
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messages',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Magento_Checkout/js/model/error-processor',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/model/adyen-checkout',
    'Adyen_Payment/js/model/adyen-payment-modal',
    'Adyen_Payment/js/model/installments',
], function (
    $,
    ko,
    layout,
    url,
    placeOrderAction,
    fullScreenLoader,
    quote,
    Messages,
    VaultComponent,
    errorProcessor,
    adyenPaymentService,
    adyenConfiguration,
    adyenCheckout,
    adyenPaymentModal,
    installmentsHelper
) {
    'use strict';

    let isValidObserver = ko.observable(false);
    let validTokens = {};

    return VaultComponent.extend({
        defaults: {
            template: 'Adyen_Payment/payment/card-vault-form.html',
            checkoutComponentBuilt: false,
            modalLabel: null
        },

        initObservable: function () {
            this._super()
                .observe([
                    'checkoutComponentBuilt',
                    'installment'
                ]);

            return this;
        },

        initialize: function () {
            let self = this;
            this._super();
            this.modalLabel = 'card_action_modal_' + this.getId();
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
            let self = this;
            let request = result.data;

            fullScreenLoader.stopLoader();

            let popupModal = self.showModal();

            adyenPaymentService.paymentDetails(request, self.orderId).done(function (responseJSON) {
                self.handleAdyenResult(responseJSON,
                    self.orderId);
            }).fail(function (response) {
                self.closeModal(popupModal);
                errorProcessor.process(response, self.messageContainer);
                self.isPlaceOrderActionAllowed(true);
                fullScreenLoader.stopLoader();
            });
        },

        renderCardVaultToken: function () {
            let self = this;
            if (!this.getClientKey()) {
                return false
            }

            // let installmentAmounts = window.checkoutConfig.payment.adyenVault.installmentAmounts;
            // TODO2 when you obtain the installments, format the amount with installments helper - check the usage

            // let installmentAmounts = {
            //     "visa": {
            //         "values": [
            //             1,
            //             2
            //         ]
            //     },
            //     "mc": {
            //         "values": [
            //             1,
            //             2
            //         ]
            //     },
            //     "jcb": {
            //         "values": [
            //             1,
            //             2
            //         ]
            //     },
            //     "maestro": {
            //         "values": [
            //             1,
            //             2
            //         ]
            //     },
            //     "cup": {
            //         "values": [
            //             1,
            //             2
            //         ]
            //     },
            //     "bcmc": {
            //         "values": [
            //             1,
            //             2
            //         ]
            //     },
            //     "cartebancaire": {
            //         "values": [
            //             1,
            //             2
            //         ]
            //     }
            // };
            debugger;

            let cardAltCode = this.getCcCodeByAltCode(self.getCardType());

            let allInstallments = window.checkoutConfig.payment.adyenCc.installments;
            let vaultInstallments = allInstallments[cardAltCode];
            debugger;
            console.log('rok was here ', vaultInstallments);
            let grandTotal = self.grandTotal();
            let precision = quote.getPriceFormat().precision;
            let currencyCode = quote.totals().quote_currency_code;


            let sanitizedInstallmentAmounts = installmentsHelper.getInstallmentsWithPrices(
                vaultInstallments,
                grandTotal,
                precision,
                currencyCode
            )

            console.log('hoi ', sanitizedInstallmentAmounts);

            let componentConfig = {
                hideCVC: false,
                brand: this.getCardType(),
                storedPaymentMethodId: this.getGatewayToken(),
                expiryMonth: this.getExpirationMonth(),
                expiryYear: this.getExpirationYear(),
                //holderName: 'First tester',
                onChange: this.handleOnChange.bind(this),
                installmentOptions: sanitizedInstallmentAmounts,
                showInstallmentAmounts: true
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
            return this.isActive() && this.isPlaceOrderActionAllowed() && isValidObserver()[this.getId()];
        },

        placeOrder: function (data, event) {
            let self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() && this.isPlaceOrderActionAllowed() === true) {
                fullScreenLoader.startLoader();
                this.isPlaceOrderActionAllowed(false);
                this.getPlaceOrderDeferredObject().done(
                    function (orderId) {
                        self.orderId = orderId;
                        adyenPaymentService.getOrderPaymentStatus(orderId).done(function (responseJSON) {
                            self.handleAdyenResult(responseJSON, orderId);
                        });
                    },
                ).fail(
                    function () {
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                    }
                );

                return true;
            }

            return false;
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
                    public_hash: this.publicHash,
                    numberOfInstallments: this.installment()
                },
            };
        },

        handleAdyenResult: function (responseJSON, orderId) {
            let self = this;
            const response = JSON.parse(responseJSON);

            if (!!response.isFinal) {
                // Status is final redirect to the success page
                window.location.replace(url.build(this.successPage));
            } else {
                self.handleAction(response.action, orderId);
            }
        },

        handleAction: function (action, orderId) {
            let self = this;
            let popupModal;

            if (action.type === 'threeDS2' || action.type === 'await') {
                popupModal = self.showModal();
            }
            try {
                // Determine threeDS2 modal size, based on screen width
                const threeDSConfiguration = {
                    challengeWindowSize: screen.width < 460 ? '01' : '02'
                }

                this.checkoutComponent.createFromAction(action, threeDSConfiguration).mount(
                    '#' + this.modalLabel + 'Content'
                );

                fullScreenLoader.stopLoader();
            } catch (e) {
                console.log(e);
                self.closeModal(popupModal);
            }
        },

        showModal: function() {
            let actionModal = adyenPaymentModal.showModal(adyenPaymentService, fullScreenLoader, this.messageContainer, this.orderId, this.modalLabel, this.isPlaceOrderActionAllowed)
            $("." + this.modalLabel + " .action-close").hide();

            return actionModal;
        },

        closeModal: function(popupModal) {
            adyenPaymentModal.closeModal(popupModal, this.modalLabel)
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

        getCode: function() {
            return window.checkoutConfig.payment.adyenCc.methodCode;
        },

        getCcCodeByAltCode: function(altCode) {
            let ccTypes = window.checkoutConfig.payment.ccform.availableTypesByAlt[this.getCode()];
            if (ccTypes.hasOwnProperty(altCode)) {
                return ccTypes[altCode];
            }

            return '';
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

        grandTotal: function () {
            for (const totalsegment of quote.getTotals()()['total_segments']) {
                if (totalsegment.code === 'grand_total') {
                    return totalsegment.value;
                }
            }
            return quote.totals().grand_total;
        },

        getPlaceOrderDeferredObject: function () {
            return $.when(placeOrderAction(this.getData()));
        }
    });
});
