/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
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
            template: 'Adyen_Payment/payment/cc-vault-form',
            modalLabel: null,
            installment: ''
        },
        checkoutComponent: null,

        initObservable: function () {
            this._super()
                .observe([
                    'installment',
                    'installments',
                    'adyenVaultPaymentMethod'
                ]);

            return this;
        },

        initialize: function () {
            let self = this;
            this._super();
            this.modalLabel = 'card_action_modal_' + this.getId();

            let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
            paymentMethodsObserver.subscribe(function (paymentMethodsResponse) {
                self.enablePaymentMethod(paymentMethodsResponse)
            });

            if(!!paymentMethodsObserver()) {
                self.enablePaymentMethod(paymentMethodsObserver());
            }

            return this;
        },

        enablePaymentMethod: function (paymentMethodsResponse) {
            if (!!paymentMethodsResponse.paymentMethodsResponse) {
                this.adyenVaultPaymentMethod(true);
                fullScreenLoader.stopLoader();
            }
        },

        /*
         * Create generic AdyenCheckout library and mount payment method component
         * after selecting the payment method via overriding parent `selectPaymentMethod()` function.
         */
        selectPaymentMethod: function () {
            this._super();
            this.createCheckoutComponent();

            return true;
        },

        /*
         * Pre-selected payment methods don't trigger parent's `selectPaymentMethod()` function.
         *
         * This function is triggered via `afterRender` attribute of the html template
         * and creates checkout component for pre-selected payment method.
         */
        renderPreSelected: function () {
            if (this.isChecked() === this.getCode()) {
                this.createCheckoutComponent();
            }
        },

        // Build AdyenCheckout library and creates the payment method component
        createCheckoutComponent: async function() {
            if (!this.checkoutComponent) {
                const paymentMethodsResponse = adyenPaymentService.getPaymentMethods();

                this.checkoutComponent = await adyenCheckout.buildCheckoutComponent(
                    paymentMethodsResponse(),
                    this.handleOnAdditionalDetails.bind(this)
                );
            }

            this.renderCheckoutComponent();
        },

        handleOnAdditionalDetails: function (result) {
            let self = this;
            let request = result.data;
            adyenPaymentModal.hideModalLabel(this.modalLabel);
            fullScreenLoader.startLoader();

            adyenPaymentService.paymentDetails(request, self.orderId).done(function (responseJSON) {
                self.handleAdyenResult(responseJSON, self.orderId);
            }).fail(function (response) {
                self.closeModal(popupModal);
                errorProcessor.process(response, self.messageContainer);
                self.isPlaceOrderActionAllowed(true);
                fullScreenLoader.stopLoader();
            });
        },

        renderCheckoutComponent: function () {
            let self = this;
            if (!this.getClientKey()) {
                return false;
            }

            let requireCvc = window.checkoutConfig.payment.adyenCc.requireCvc;
            const allInstallments = self.getAllInstallments();

            let componentConfig = {
                hideCVC: !requireCvc,
                brand: this.getCardType(),
                storedPaymentMethodId: this.getGatewayToken(),
                expiryMonth: this.getExpirationMonth(),
                expiryYear: this.getExpirationYear(),
                onChange: this.handleOnChange.bind(this)
            };

            // Always try to initialize installments based on the stored card type
            const brand = self.getCardType();
            const creditCardType = self.getCcCodeByAltCode(brand);
            let numberOfInstallments = [];

            if (creditCardType) {
                if (creditCardType in allInstallments) {
                    const cardInstallments = allInstallments[creditCardType];
                    const grandTotal = self.grandTotal();
                    const precision = quote.getPriceFormat().precision;
                    const currencyCode = quote.totals().quote_currency_code;

                    numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(
                        cardInstallments, grandTotal,
                        precision, currencyCode
                    );
                }
            }

            self.installments(numberOfInstallments.length ? numberOfInstallments : 0);

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
                    'number_of_installments': self.installment(),
                    frontendType: 'default',
                    'cc_type': self.getCcCodeByAltCode(self.getCardType())
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
                const actionComponentConfiguration = {
                    challengeWindowSize: screen.width < 460 ? '01' : '02',
                    onActionHandled: function (event) {
                        if (event.componentType === "3DS2Challenge") {
                            fullScreenLoader.stopLoader();
                            popupModal.modal('openModal');
                        }
                    }
                }

                this.checkoutComponent.createFromAction(action, actionComponentConfiguration).mount(
                    '#' + this.modalLabel + 'Content'
                );
            } catch (e) {
                console.log(e);
                self.closeModal(popupModal);
            }
        },

        showModal: function() {
            let actionModal = adyenPaymentModal.showModal(
                adyenPaymentService,
                fullScreenLoader,
                this.messageContainer,
                this.orderId,
                this.modalLabel,
                this.isPlaceOrderActionAllowed,
                false
            );

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

        hasInstallments: function() {
            return !!window.checkoutConfig.payment.adyenCc.hasInstallments;
        },

        getAllInstallments: function() {
            return window.checkoutConfig.payment.adyenCc.installments;
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
