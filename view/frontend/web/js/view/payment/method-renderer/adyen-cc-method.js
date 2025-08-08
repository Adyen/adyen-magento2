/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'jquery',
        'ko',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/model/installments',
        'mage/url',
        'Magento_Vault/js/view/payment/vault-enabler',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/model/adyen-payment-modal',
        'Adyen_Payment/js/model/adyen-checkout'
    ],
    function(
        $,
        ko,
        Component,
        customer,
        additionalValidators,
        quote,
        installmentsHelper,
        url,
        VaultEnabler,
        urlBuilder,
        fullScreenLoader,
        errorProcessor,
        adyenPaymentService,
        adyenConfiguration,
        AdyenPaymentModal,
        adyenCheckout
    ) {
        'use strict';
        return Component.extend({
            // need to duplicate as without the button will never activate on first time page view
            isPlaceOrderActionAllowed: ko.observable(
                quote.billingAddress() != null),
            comboCardOption: ko.observable(),
            checkoutComponent: null,
            cardComponent: null,

            defaults: {
                template: 'Adyen_Payment/payment/cc-form',
                installment: '', // keep it until the component implements installments
                orderId: 0, // TODO is this the best place to store it?
                storeCc: false,
                modalLabel: 'cc_actionModal'
            },
            initObservable: function() {
                this._super().observe([
                    'creditCardType',
                    'installment',
                    'installments',
                    'placeOrderAllowed',
                    'adyenCCMethod',
                    'logo'
                ]);

                return this;
            },
            /**
             * @returns {exports.initialize}
             */
            initialize: function () {
                this._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
                this.vaultEnabler.isActivePaymentTokenEnabler(false);

                let self = this;

                let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
                paymentMethodsObserver.subscribe(
                    function (paymentMethodsResponse) {
                        self.enablePaymentMethod(paymentMethodsResponse)
                    }
                );

                if(!!paymentMethodsObserver()) {
                    self.enablePaymentMethod(paymentMethodsObserver());
                }
            },
            isSchemePaymentsEnabled: function (paymentMethod) {
                return paymentMethod.type === "scheme";
            },

            /*
             * Enables the payment method and sets the required attributes
             * if `/paymentMethods` response contains this payment method.
             */
            enablePaymentMethod: async function (paymentMethodsResponse) {
                let self = this;

                // Check the paymentMethods response to enable Credit Card payments
                if (!!paymentMethodsResponse &&
                    !paymentMethodsResponse.paymentMethodsResponse?.paymentMethods.find(self.isSchemePaymentsEnabled)) {
                    return;
                }

                self.adyenCCMethod({
                    icon: !!paymentMethodsResponse.paymentMethodsExtraDetails.card
                        ? paymentMethodsResponse.paymentMethodsExtraDetails.card.icon
                        : undefined
                })
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
            createCheckoutComponent: async function () {
                if (!this.checkoutComponent) {
                    const paymentMethodsResponse = adyenPaymentService.getPaymentMethods();

                    this.checkoutComponent = await adyenCheckout.buildCheckoutComponent(
                        paymentMethodsResponse(),
                        this.handleOnAdditionalDetails.bind(this)
                    )
                }

                this.renderCCPaymentMethod();
            },
            /**
             * Returns true if card details can be stored
             * @returns {*|boolean}
             */
            getEnableStoreDetails: function () {
                return this.isCardRecurringEnabled() && this.isVaultEnabled();
            },
            /**
             * Renders the secure fields,
             * creates the card component,
             * sets up the callbacks for card components and
             * set up the installments
             */
            renderCCPaymentMethod: function() {
                if (!this.cardComponent) {
                    let componentConfig = this.buildComponentConfiguration();

                    this.cardComponent = adyenCheckout.mountPaymentMethodComponent(
                        this.checkoutComponent,
                        'card',
                        componentConfig,
                        '#cardContainer'
                    )
                }
            },

            buildComponentConfiguration: function () {
                let self = this;

                if (!this.getClientKey) {
                    return false;
                }

                this.installments(0);
                let allInstallments = this.getAllInstallments();

                let componentConfig = {
                    showPayButton: false,
                    enableStoreDetails: this.getEnableStoreDetails(),
                    brands: this.getBrands(),
                    hasHolderName: adyenConfiguration.getHasHolderName(),
                    holderNameRequired: adyenConfiguration.getHasHolderName() &&
                        adyenConfiguration.getHolderNameRequired(),
                    onChange: function(state, component) {
                        self.placeOrderAllowed(!!state.isValid);
                        self.storeCc = !!state.data.storePaymentMethod;
                    },
                    // Required for Click to Pay
                    onSubmit: function () {
                        self.placeOrder();
                    },
                    // Keep onBrand as is until checkout component supports installments
                    onBrand: function(state) {
                        // Define the card type
                        // translate adyen card type to magento card type
                        let creditCardType = self.getCcCodeByAltCode(
                            state.brand);
                        if (creditCardType) {
                            // If the credit card type is already set, check if it changed or not
                            if (!self.creditCardType() ||
                                self.creditCardType() &&
                                self.creditCardType() != creditCardType) {
                                let numberOfInstallments = [];

                                if (creditCardType in allInstallments) {
                                    // get for the creditcard the installments
                                    let cardInstallments = allInstallments[creditCardType];
                                    let grandTotal = self.grandTotal();
                                    let precision = quote.getPriceFormat().precision;
                                    let currencyCode = quote.totals().quote_currency_code;

                                    numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(
                                        cardInstallments, grandTotal,
                                        precision, currencyCode);
                                }

                                if (numberOfInstallments) {
                                    self.installments(numberOfInstallments);
                                } else {
                                    self.installments(0);
                                }
                            }

                            self.creditCardType(creditCardType);
                        } else {
                            self.creditCardType('');
                            self.installments(0);
                        }
                    }
                }

                if (this.isClickToPayEnabled()) {
                    componentConfig.clickToPayConfiguration = {
                        merchantDisplayName: adyenConfiguration.getMerchantAccount(),
                        shopperEmail: this.getShopperEmail()
                    };
                }

                return componentConfig;
            },

            handleAction: function(action, orderId) {
                let self = this;
                let popupModal;

                if (action.type === 'threeDS2' || action.type === 'await') {
                    popupModal = self.showModal();
                }

                try {
                    self.checkoutComponent.createFromAction(action, {
                        onActionHandled: function (event) {
                            if (event.componentType === "3DS2Challenge") {
                                fullScreenLoader.stopLoader();
                                popupModal.modal('openModal');
                            }
                        }
                    }).mount('#' + this.modalLabel);
                } catch (e) {
                    console.log(e);
                    self.closeModal(popupModal);
                }
            },
            showModal: function() {
                let actionModal = AdyenPaymentModal.showModal(
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
            /**
             * This method is a workaround to close the modal in the right way and reconstruct the threeDS2Modal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function(popupModal) {
                AdyenPaymentModal.closeModal(popupModal, this.modalLabel)
            },
            /**
             * Get data for place order
             * @returns {{method: *}}
             */
            getData: function() {
                let data = {
                    'method': this.item.method,
                    additional_data: {
                        'stateData': {},
                        'guestEmail': quote.guestEmail,
                        'frontendType': 'default'
                    }
                };

                if (!!this.comboCardOption()) {
                    data.additional_data.combo_card_type = this.comboCardOption();
                }

                if (!!this.creditCardType()) {
                    data.additional_data.cc_type = this.creditCardType();
                }

                if (!!this.installment()) {
                    data.additional_data.number_of_installments = this.installment();
                }

                // This is required by magento to store the token
                if (this.storeCc) {
                    data.additional_data.is_active_payment_token_enabler = true;
                }

                // Get state data only if the checkout component is ready,
                if (this.checkoutComponent) {
                    const stateData = JSON.stringify(this.cardComponent.data)
                    data.additional_data.stateData = stateData;
                    window.sessionStorage.setItem('adyen.stateData', stateData);
                }

                return data;
            },
            /**
             * Returns state of place order button
             * @returns {boolean}
             */
            isButtonActive: function() {
                // TODO check if isPlaceOrderActionAllowed and placeOrderAllowed are both needed
                return this.isActive() && this.getCode() == this.isChecked() &&
                    this.isPlaceOrderActionAllowed() &&
                    this.placeOrderAllowed();
            },
            /**
             * Custom place order function
             *
             * @override
             *
             * @param data
             * @param event
             * @returns {boolean}
             */
            placeOrder: function(data, event) {
                let self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    fullScreenLoader.startLoader();
                    self.isPlaceOrderActionAllowed(false);

                    self.getPlaceOrderDeferredObject().fail(
                        function() {
                            fullScreenLoader.stopLoader();
                            self.isPlaceOrderActionAllowed(true);
                        }
                    ).done(
                        function(orderId) {
                            self.afterPlaceOrder();
                            self.orderId = orderId;
                            adyenPaymentService.getOrderPaymentStatus(orderId).
                                done(function(responseJSON) {
                                    self.handleAdyenResult(responseJSON,
                                        orderId);
                                });
                        }
                    );
                }
                return false;
            },
            /**
             * Based on the response we can start a 3DS2 validation or place the order
             * @param responseJSON
             */
            handleAdyenResult: function(responseJSON, orderId) {
                let self = this;
                let response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the success page
                    window.location.replace(url.build(
                        window.checkoutConfig.payment[quote.paymentMethod().method].successPage
                    ));
                } else {
                    // Handle action
                    self.handleAction(response.action, orderId);
                }
            },
            handleOnAdditionalDetails: function(result) {
                const self = this;
                let request = result.data;
                AdyenPaymentModal.hideModalLabel(this.modalLabel);
                fullScreenLoader.startLoader();
                let popupModal = self.showModal();

                adyenPaymentService.paymentDetails(request, self.orderId).
                    done(function(responseJSON) {
                        self.handleAdyenResult(responseJSON, self.orderId);
                    }).
                    fail(function(response) {
                        self.closeModal(popupModal);
                        errorProcessor.process(response, self.messageContainer);
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                    });
            },
            /**
             * Validates the payment date when clicking the pay button
             *
             * @returns {boolean}
             */
            validate: function() {
                let form = 'form[data-role=adyen-cc-form]';

                let validate = $(form).validation() &&
                    $(form).validation('isValid') &&
                    this.cardComponent.isValid;

                if (!validate) {
                    this.cardComponent.showValidation();
                    return false;
                }

                return true;
            },

            /**
             * Translates the card type alt code (used in Adyen) to card type code (used in Magento) if it's available
             *
             * @param altCode
             * @returns {*}
             */
            getCcCodeByAltCode: function(altCode) {
                let ccTypes = window.checkoutConfig.payment.ccform.availableTypesByAlt[this.getCode()];
                if (ccTypes.hasOwnProperty(altCode)) {
                    return ccTypes[altCode];
                }

                return '';
            },

            /**
             * Fetches the brands array of the credit cards
             *
             * @returns {array}
             */
            getBrands: function() {
                const methods = adyenPaymentService.getPaymentMethods()();
                if (!methods.paymentMethodsResponse) {
                    return [];
                }

                for (const method of methods.paymentMethodsResponse.paymentMethods) {
                    if (method.type === 'scheme' && method.brands) {
                        return method.brands;
                    }
                }
                return [];
            },
            /**
             * Return Payment method code
             *
             * @returns {*}
             */
            getCode: function() {
                return window.checkoutConfig.payment.adyenCc.methodCode;
            },
            isCardRecurringEnabled: function () {
                if (customer.isLoggedIn()) {
                    return window.checkoutConfig.payment.adyenCc.isCardRecurringEnabled;
                }

                return false;
            },
            getShopperEmail: function () {
                if (customer.isLoggedIn()) {
                    return customer.customerData.email;
                } else {
                    return quote.guestEmail;
                }
            },
            isClickToPayEnabled: function () {
                return window.checkoutConfig.payment.adyenCc.isClickToPayEnabled;
            },
            getIcons: function(type) {
                return window.checkoutConfig.payment.adyenCc.icons.hasOwnProperty(
                    type)
                    ? window.checkoutConfig.payment.adyenCc.icons[type]
                    : false;
            },
            hasInstallments: function() {
                return this.comboCardOption() !== 'debit' &&
                    window.checkoutConfig.payment.adyenCc.hasInstallments;
            },
            getAllInstallments: function() {
                return window.checkoutConfig.payment.adyenCc.installments;
            },
            areComboCardsEnabled: function() {
                if (quote.billingAddress() === null) {
                    return false;
                }
                let countryId = quote.billingAddress().countryId;
                let currencyCode = quote.totals().quote_currency_code;
                let allowedCurrenciesByCountry = {
                    'BR': 'BRL'
                };
                return allowedCurrenciesByCountry[countryId] &&
                    currencyCode === allowedCurrenciesByCountry[countryId];
            },
            getClientKey: function() {
                return adyenConfiguration.getClientKey();
            },
            /**
             * @returns {Bool}
             */
            isVaultEnabled: function() {
                return this.vaultEnabler.isVaultEnabled();
            },
            /**
             * @returns {String}
             */
            getVaultCode: function() {
                return window.checkoutConfig.payment[this.getCode()].vaultCode;
            },

            // Default payment functions
            setPlaceOrderHandler: function(handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function(handler) {
                this.validateHandler = handler;
            },
            context: function() {
                return this;
            },
            isShowLegend: function() {
                return true;
            },
            showLogo: function() {
                return adyenConfiguration.showLogo();
            },
            isActive: function() {
                return true;
            },
            getControllerName: function() {
                return window.checkoutConfig.payment.adyenCc.controllerName;
            },
            grandTotal: function () {
                for (const totalsegment of quote.getTotals()()['total_segments']) {
                    if (totalsegment.code === 'grand_total') {
                        return totalsegment.value;
                    }
                }
                return quote.totals().grand_total;
            },
            getPaymentMethodComponent: function () {
                return this.cardComponent;
            }
        });
    }
);
