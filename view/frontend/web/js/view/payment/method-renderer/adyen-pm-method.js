/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/model/adyen-payment-modal',
        'Adyen_Payment/js/model/adyen-checkout',
        'Adyen_Payment/js/model/payment-component-states',
        'Adyen_Payment/js/helper/disclaimerHelper',
    ],
    function(
        ko,
        $,
        Component,
        quote,
        additionalValidators,
        adyenPaymentService,
        fullScreenLoader,
        placeOrderAction,
        errorProcessor,
        adyenConfiguration,
        adyenPaymentModal,
        adyenCheckout,
        paymentComponentStates,
        disclaimerHelper
    ) {
        'use strict';

        return Component.extend({
            placeOrderButtonVisible: true,
            checkoutComponent: null,
            paymentComponent: null,
            popupModal: null,
            _componentBuildId: 0,
            _componentBuild: null,
            _renderedAmount: null,
            _buildWhenBillingAddressSet: false,
            defaults: {
                template: 'Adyen_Payment/payment/pm-form',
                orderId: null,
                modalLabel: 'hpp_actionModal'
            },

            initObservable: function() {
                this._super().observe([
                    'paymentMethod',
                    'paymentMethodsExtraInfo',
                    'adyenPaymentMethod',
                    'isPlaceOrderActionAllowed',
                    'placeOrderAllowed'
                ]);

                return this;
            },

            initialize: function() {
                this._super();
                let self = this;

                let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
                paymentMethodsObserver.subscribe(
                    function(paymentMethodsResponse) {
                        self.enablePaymentMethod(paymentMethodsResponse);
                        self.rebuildComponentIfAmountChanged();
                    }
                );

                if(!!paymentMethodsObserver()) {
                    self.enablePaymentMethod(paymentMethodsObserver());
                }

                // Seed the baseline from the current total so the first totals emission doesn't trigger
                // a rebuild that races with the initial mount and can leave wallet buttons unrendered.
                // A missing/null total is treated as "no baseline yet", consistent with the guard below.
                const initialTotals = quote.totals();
                this._lastGrandTotal = (initialTotals && initialTotals.grand_total !== null) ? initialTotals.grand_total : undefined;

                // This event is triggered when the total changes or billing address is added (virtual products)
                quote.totals.subscribe(function (totals) {
                    if (!totals) {
                        return;
                    }
                    const newGrandTotal = totals.grand_total;
                    // Ignore emissions without a usable total so a transient empty/cleared totals
                    // object can't corrupt the baseline and suppress a later legitimate rebuild.
                    if (newGrandTotal === undefined || newGrandTotal === null) {
                        return;
                    }
                    const previousGrandTotal = self._lastGrandTotal;

                    if (Number(newGrandTotal) === Number(previousGrandTotal)) {
                        return;
                    }

                    self._lastGrandTotal = newGrandTotal;

                    // Establish the baseline on the first total without rebuilding.
                    if (typeof previousGrandTotal === 'undefined') {
                        return;
                    }

                    // Rebuild component with updated configuration
                    if (self.isChecked() === self.getCode() && !self.isWaitingForAmount()) {
                        self.rebuildComponentAfterAmountChange();
                    }
                });

                // A build that could not start without a billing country runs once the country is set.
                quote.billingAddress.subscribe(function (billingAddress) {
                    if (self._buildWhenBillingAddressSet && billingAddress && billingAddress.countryId
                        && self.isChecked() === self.getCode()) {
                        self.rebuildComponentAfterAmountChange();
                    }
                });

                paymentComponentStates().initializeState(this.getMethodCode());
            },

            getTitle: function () {
                const paymentMethodsObservable = adyenPaymentService.getPaymentMethods();
                const methodCode = this.getTxVariant();
                const methods = paymentMethodsObservable?.()?.paymentMethodsResponse?.paymentMethods;

                if (Array.isArray(methods)) {
                    const matchedMethod = methods.find(pm => pm.type === methodCode);
                    if (matchedMethod?.name) {
                        return matchedMethod.name;
                    }
                }

                return this._super();
            },

            enablePaymentMethod: function (paymentMethodsResponse) {
                if (this.checkBrowserCompatibility() && !!paymentMethodsResponse.paymentMethodsResponse) {
                    this.paymentMethod(
                        adyenPaymentService.getPaymentMethodFromResponse(
                            this.getTxVariant(),
                            paymentMethodsResponse.paymentMethodsResponse.paymentMethods
                        )
                    );

                    if (!!this.paymentMethod()) {
                        this.paymentMethodsExtraInfo(paymentMethodsResponse.paymentMethodsExtraDetails);
                        // Setting the icon and method txvariant as an accessible field if it is available
                        this.adyenPaymentMethod({
                            icon: !!paymentMethodsResponse.paymentMethodsExtraDetails[this.getTxVariant()]
                                ? paymentMethodsResponse.paymentMethodsExtraDetails[this.getTxVariant()].icon
                                : undefined,
                            method: this.getTxVariant()
                        });
                    }

                    fullScreenLoader.stopLoader();
                }
            },

            /*
             * Create generic AdyenCheckout library and mount payment method component
             * after selecting the payment method via overriding parent `selectPaymentMethod()` function.
             */
            selectPaymentMethod: function () {
                this._super();

                // No component is mounted, or the amount changed while another payment method was selected.
                if (this.checkoutComponent && (!this.paymentComponent || this.hasAmountChanged())) {
                    this.rebuildComponentAfterAmountChange();
                } else {
                    this.createCheckoutComponent();
                }

                return true;
            },

            rebuildComponentAfterAmountChange: function () {
                // Unmount existing component if present
                adyenCheckout.disposePaymentMethodComponent(this.paymentComponent);
                this.paymentComponent = null;

                // Force re-create component with updated configuration. checkoutComponent is kept until the new one
                // renders, so an action for a payment that is already submitted (e.g. 3DS) can still be handled.
                this.createCheckoutComponent(true);
            },

            /*
             * Components that show an amount take it from the payment methods response. Magento stores the new
             * quote totals before that response, so these components are rebuilt here instead of on the totals.
             */
            rebuildComponentIfAmountChanged: function () {
                if (this.isChecked() === this.getCode() && this.hasAmountChanged()) {
                    this.rebuildComponentAfterAmountChange();
                }
            },

            isWaitingForAmount: function () {
                return !!this._renderedAmount && !this.hasAmountChanged();
            },

            hasAmountChanged: function () {
                if (!this._renderedAmount) {
                    return false;
                }

                const paymentMethod = this.paymentMethod();
                const amount = this.getAmountKey(
                    paymentMethod && this.paymentMethodsExtraInfo()?.[paymentMethod.type]?.configuration?.amount
                );

                return amount !== null && amount !== this._renderedAmount;
            },

            getAmountKey: function (amount) {
                return amount ? amount.currency + ' ' + amount.value : null;
            },

            /*
             * Pre-selected payment methods don't trigger parent's `selectPaymentMethod()` function.
             *
             * This function is triggered via `afterRender` attribute of the html template
             * and creates checkout component for pre-selected payment method.
             */
            renderPreSelected: async function () {
                if (this.isChecked() === this.getCode()) {
                    await this.createCheckoutComponent();
                }
            },

            // Build AdyenCheckout library and creates the payment method component
            createCheckoutComponent: async function(forceCreate = false) {
                const billingAddressObservable = quote.billingAddress;
                let self = this;

                if (billingAddressObservable() && billingAddressObservable().countryId) {
                    let ownBuild = null;

                    if (!this.checkoutComponent || forceCreate) {
                        const buildId = ++this._componentBuildId;
                        const paymentMethodsResponse = adyenPaymentService.getPaymentMethods();

                        this._buildWhenBillingAddressSet = false;
                        ownBuild = this._componentBuild = Promise.resolve(adyenCheckout.buildCheckoutComponent(
                            paymentMethodsResponse(),
                            billingAddressObservable().countryId,
                            this.handleOnAdditionalDetails.bind(this),
                            this.handleOnCancel.bind(this),
                            this.handleOnSubmit.bind(this),
                            this.handleOnError.bind(this)
                        )).then((checkoutComponent) => {
                            // Only the newest build renders, as an older one has an outdated configuration.
                            if (buildId === this._componentBuildId) {
                                this.checkoutComponent = checkoutComponent;
                                this.renderCheckoutComponent();
                            }
                        });
                    }

                    // Callers expect the component to be mounted when this resolves, so wait for the newest build.
                    // A failure is passed on only if this call started the newest build.
                    let build;
                    do {
                        build = this._componentBuild;
                        try {
                            await build;
                        } catch (e) {
                            if (build === ownBuild && build === this._componentBuild) {
                                throw e;
                            }
                        }
                    } while (build !== this._componentBuild);
                } else {
                    this._buildWhenBillingAddressSet = true;
                }
            },

            handleOnError: function (error, component) {
                this.handleOnFailure(error, component);
            },

            handleOnFailure: function(error, component) {
                paymentComponentStates().setIsPlaceOrderAllowed(this.getMethodCode(), true);

                fullScreenLoader.stopLoader();
                errorProcessor.process(error, this.currentMessageContainer);
            },

            renderCheckoutComponent: function() {
                let methodCode = this.getMethodCode();
                let configuration = this.buildComponentConfiguration(this.paymentMethod(), this.paymentMethodsExtraInfo());

                // Forced builds do not all go through rebuildComponentAfterAmountChange(), e.g. Amazon Pay's remount.
                adyenCheckout.disposePaymentMethodComponent(this.paymentComponent);
                this.paymentComponent = null;

                this.mountPaymentMethodComponent(this.paymentMethod(), configuration, methodCode);
                this._renderedAmount = this.paymentComponent ? this.getAmountKey(configuration.amount) : null;
            },

            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let self = this;
                let showPayButton = false;

                let formattedShippingAddress = {};
                let formattedBillingAddress = {};

                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (!!quote.billingAddress()) {
                    formattedBillingAddress = self.getFormattedAddress(quote.billingAddress());
                }
                /* Use the storedPaymentMethod object and the custom onChange function as the configuration object together */
                let configuration = Object.assign(paymentMethod,
                    {
                        showPayButton: showPayButton,
                        countryCode: formattedShippingAddress.country ? formattedShippingAddress.country : formattedBillingAddress.country, // Use shipping address details as default and fall back to billing address if missing
                        onChange: function (state) {
                            paymentComponentStates().setIsPlaceOrderAllowed(self.getMethodCode(), state.isValid);
                        },
                        onSubmit: this.handleOnSubmit.bind(this),
                        onError: this.handleOnError.bind(this)
                    });

                if (adyenConfiguration.getOpenInvoicePaymentMethods().includes(self.getMethodCode())) {
                    const disclaimerMessage = disclaimerHelper.buildDisclaimerMessage(
                        adyenConfiguration.getBnplDisclaimer()
                    );

                    if (disclaimerMessage) {
                        configuration.disclaimerMessage = disclaimerMessage;
                    }
                }

                return configuration;
            },

            mountPaymentMethodComponent: function(paymentMethod, configuration, methodCode) {
                try {
                    const containerId = '#' + paymentMethod.type + 'Container';

                    this.paymentComponent = adyenCheckout.mountPaymentMethodComponent(
                        this.checkoutComponent,
                        this.getTxVariant(),
                        configuration,
                        containerId
                    );

                    if (this.paymentComponent) {
                        paymentComponentStates().setIsPlaceOrderAllowed(
                            methodCode,
                            this.paymentComponent.isValid
                        );
                    }
                } catch (err) {
                    if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                        console.error(err);
                    }
                }
            },

            handleOnSubmit: async function(state, component, actions) {
                if (this.validate()) {
                    let data = {};
                    data.method = this.getCode();

                    let additionalData = {};
                    let stateData = state.data;
                    additionalData.stateData = JSON.stringify(stateData);
                    data.additional_data = additionalData;

                    await this.placeRedirectOrder(data, component, actions);
                } else {
                    actions.reject();
                }
            },

            handleOnAdditionalDetails: function(state, component, actions) {
                adyenPaymentModal.hideModalLabel(this.modalLabel);
                fullScreenLoader.startLoader();

                this.handlePaymentsDetails(state.data, actions);
            },

            handlePaymentsDetails: function (request, actions = null) {
                const self = this;

                adyenPaymentService.paymentDetails(request, self.orderId).done(function(responseJson) {
                    self.handlePaymentsDetailsResponse(responseJson, actions)
                }).fail(function(response) {
                    self.handleFailingPaymentsDetailsResponse(actions)
                    errorProcessor.process(response, self.currentMessageContainer);
                });
            },

            handlePaymentsDetailsResponse: function (responseJSON, actions = null) {
                const response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    if (['Authorised', 'Received', 'PresentToShopper'].includes(response.resultCode)) {
                        if (!!actions) {
                            actions.resolve({resultCode: response.resultCode});
                        }

                        $.mage.redirect(
                            window.checkoutConfig.payment.adyen.successPage,
                        );
                    } else {
                        errorProcessor.process({responseText: responseJSON}, this.currentMessageContainer);
                        this.handleFailingPaymentsDetailsResponse(actions);
                    }
                }
            },

            handleFailingPaymentsDetailsResponse: function (actions = null) {
                if (!!actions) {
                    actions.reject();
                }

                fullScreenLoader.stopLoader();
                if (this.popupModal) {
                    this.closeModal(this.popupModal);
                }

                paymentComponentStates().setIsPlaceOrderAllowed(this.getMethodCode(), true);
            },

            handleOnCancel: function(state, component) {
                this.handlePaymentsDetails(state.data);
            },

            placeOrder: function() {
                let methodCode = this.getMethodCode();

                if (this.paymentComponent) {
                    this.paymentComponent.showValidation();
                }

                if (this.validate()) {
                    let data = {
                        'method': methodCode
                    };

                    let additionalData = {
                        frontendType: 'default'
                    };

                    let stateData;
                    if (this.paymentComponent) {
                        stateData = this.paymentComponent.data;
                    } else {
                        stateData = {
                            paymentMethod: {
                                type: this.paymentMethod().type,
                            },
                        };
                    }

                    additionalData.stateData = JSON.stringify(stateData);
                    data.additional_data = additionalData;

                    this.placeRedirectOrder(data, this.paymentComponent);
                } else {
                    paymentComponentStates().setIsPlaceOrderAllowed(methodCode, true);
                }

                return false;
            },

            placeRedirectOrder: async function(data, component, actions = null) {
                const self = this;

                fullScreenLoader.startLoader();
                $('.hpp-message').slideUp();
                paymentComponentStates().setIsPlaceOrderAllowed(this.getMethodCode(), false);

                try {
                    const orderId = await placeOrderAction(data, self.currentMessageContainer);
                    const responseJSON = await adyenPaymentService.getOrderPaymentStatus(orderId);

                    if (!!actions) {
                        const response = JSON.parse(responseJSON);
                        actions.resolve({resultCode: response.resultCode});
                    }

                    self.validateActionOrPlaceOrder(responseJSON, orderId, component);
                } catch (response) {
                    if (!!actions) {
                        actions.reject();
                    }
                    self.handleOnFailure(response, component);
                }
            },

            getTxVariant: function () {
                return window.checkoutConfig.payment.adyen.txVariants[this.getCode()];
            },

            getMethodCode: function () {
                return this.item.method;
            },

            validate: function() {
                const form = '#adyen-' + this.getTxVariant() + '-form';
                const validate = $(form).validation() && $(form).validation('isValid');
                return validate && additionalValidators.validate();
            },

            showPlaceOrderButton: function() {
                return this.placeOrderButtonVisible;
            },

            /**
             * This method is a workaround to close the modal in the right way and reconstruct the ActionModal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function(popupModal) {
                adyenPaymentModal.closeModal(popupModal, this.modalLabel)
            },

            /**
             * Based on the response we can start a action component or redirect
             * @param responseJSON
             */
            validateActionOrPlaceOrder: function(responseJSON, orderId, component) {
                let self = this;
                let response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the success page
                    $.mage.redirect(window.checkoutConfig.payment.adyen.successPage);
                } else {
                    // render component
                    self.orderId = orderId;
                    self.renderActionComponent(response.resultCode, response.action, component);
                }
            },

            renderActionComponent: function(resultCode, action, component) {
                let self = this;
                let actionNode = document.getElementById(this.modalLabel + 'Content');

                if (resultCode !== 'RedirectShopper') {
                    let isModalVisible = true;

                    if (action.type === 'threeDS2') {
                        isModalVisible = false;
                    } else {
                        fullScreenLoader.stopLoader();
                    }

                    self.popupModal = adyenPaymentModal.showModal(
                        adyenPaymentService,
                        fullScreenLoader,
                        this.messageContainer,
                        this.orderId,
                        this.modalLabel,
                        this.isPlaceOrderActionAllowed,
                        isModalVisible
                    );

                    $("." + this.modalLabel + " .action-close").hide();
                }

                self.actionComponent = self.checkoutComponent.createFromAction(action, {
                    onActionHandled: function (event) {
                        if (event.componentType === "3DS2Challenge") {
                            fullScreenLoader.stopLoader();
                            self.popupModal.modal('openModal');
                        }
                    }
                }).mount(actionNode);
            },

            isButtonActive: function() {
                return this.isPlaceOrderActionAllowed() &&
                    paymentComponentStates().getIsPlaceOrderAllowed(this.getMethodCode());
            },

            /**
             * @param address
             * @returns {{country: (string|*), firstName: (string|*), lastName: (string|*), city: (*|string), street: *, postalCode: (*|string), houseNumber: string, telephone: (string|*)}}
             */
            getFormattedAddress: function(address) {
                function getStreetAndHouseNumberFromAddress(address, houseNumberStreetLine, customerStreetLinesEnabled) {
                    let street = address.street.slice(0, customerStreetLinesEnabled);
                    let drawHouseNumberWithRegex = parseInt(houseNumberStreetLine) === 0 || // Config is disabled
                        houseNumberStreetLine > customerStreetLinesEnabled || // Not enough street lines enabled
                        houseNumberStreetLine > street.length; // House number field is empty

                    let addressArray;
                    if (drawHouseNumberWithRegex) {
                        addressArray = getStreetAndHouseNumberWithRegex(street.join(' ').trim());
                    } else {
                        let houseNumber = street.splice(houseNumberStreetLine - 1, 1);
                        addressArray = {
                            streetName: street.join(' ').trim(),
                            houseNumber: houseNumber.join(' ').trim()
                        }
                    }
                    return addressArray;
                }

                function getStreetAndHouseNumberWithRegex(addressString) {
                    // Match addresses where the street name comes first, e.g. John-Paul's Ave. 1 B
                    const streetFirstRegex = /^(?<streetName>[a-zA-Z0-9.'\- ]{1,100})\s+(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)$/;

                    // Match addresses where the house number comes first, e.g. 10 D John-Paul's Ave.
                    const numberFirstRegex = /^(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)\s+(?<streetName>[a-zA-Z0-9.'\- ]{1,100})$/;

                    const streetFirstAddress = addressString.match(streetFirstRegex);
                    const numberFirstAddress = addressString.match(numberFirstRegex);

                    if (streetFirstAddress) {
                        return streetFirstAddress.groups;
                    } else if (numberFirstAddress) {
                        return numberFirstAddress.groups;
                    }

                    return {
                        streetName: addressString,
                        houseNumber: 'N/A'
                    };
                }

                let street = getStreetAndHouseNumberFromAddress(
                    address,
                    adyenConfiguration.getHouseNumberStreetLine(),
                    adyenConfiguration.getCustomerStreetLinesEnabled()
                );

                return {
                    city: address.city,
                    country: address.countryId,
                    postalCode: address.postcode,
                    street: street.streetName,
                    houseNumber: street.houseNumber,
                    firstName: address.firstname,
                    lastName: address.lastname,
                    telephone: address.telephone
                };
            },

            checkBrowserCompatibility: function () {
                return true;
            },

            getPaymentMethodComponent: function () {
                return this.paymentComponent;
            }
        });
    },
);
