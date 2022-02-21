/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'ko',
        'underscore',
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'uiLayout',
        'Magento_Ui/js/model/messages',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Adyen_Payment/js/adyen',
        'Adyen_Payment/js/model/adyen-configuration',
    ],
    function (
        ko,
        _,
        $,
        Component,
        selectPaymentMethodAction,
        additionalValidators,
        quote,
        checkoutData,
        layout,
        Messages,
        url,
        fullScreenLoader,
        placeOrderAction,
        errorProcessor,
        adyenPaymentService,
        AdyenCheckout,
        adyenConfiguration,
    ) {

        'use strict';

        var messageComponents;

        var recurringDetailReference = ko.observable(null);
        var variant = ko.observable(null);
        var paymentMethod = ko.observable(null);
        var numberOfInstallments = ko.observable(null);
        var isValid = ko.observable(false);

        return Component.extend({
            isPlaceOrderActionAllowed: ko.observable(
                quote.billingAddress() != null),
            defaults: {
                template: 'Adyen_Payment/payment/oneclick-form',
                recurringDetailReference: '',
                variant: '',
                numberOfInstallments: '',
            },
            initObservable: function () {
                this._super().observe([
                    'recurringDetailReference',
                    'variant',
                    'numberOfInstallments',
                ]);
                return this;
            },
            initialize: async function () {
                let self = this;
                this._super();

                // create component needs to be in initialize method
                let messageComponents = {};
                _.map(
                    window.checkoutConfig.payment.adyenOneclick.billingAgreements,
                    function (billingAgreement) {

                        let messageContainer = new Messages();
                        let name = 'messages-' + billingAgreement.reference_id;
                        let messagesComponent = {
                            parent: self.name,
                            name: 'messages-' + billingAgreement.reference_id,
                            // name: self.name + '.messages',
                            displayArea: 'messages-' +
                                billingAgreement.reference_id,
                            component: 'Magento_Ui/js/view/messages',
                            config: {
                                messageContainer: messageContainer,
                            },
                        };
                        layout([messagesComponent]);

                        messageComponents[name] = messageContainer;
                    });
                this.messageComponents = messageComponents;

                let paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
                let paymentMethodsResponse = paymentMethodsObserver();

                if (!!paymentMethodsResponse) {

                    this.checkoutComponent = await AdyenCheckout({
                            locale: adyenConfiguration.getLocale(),
                            clientKey: adyenConfiguration.getClientKey(),
                            environment: adyenConfiguration.getCheckoutEnvironment(),
                            paymentMethodsResponse: paymentMethodsResponse.paymentMethodsResponse,
                            onAdditionalDetails: this.handleOnAdditionalDetails.bind(this),
                        },
                    );
                }
            },
            handleOnAdditionalDetails: function (result) {
                var self = this;
                var request = result.data;
                request.orderId = self.orderId;

                fullScreenLoader.stopLoader();

                let popupModal = self.showModal();

                adyenPaymentService.paymentDetails(request).done(function (responseJSON) {
                    self.handleAdyenResult(responseJSON,
                        self.orderId);
                }).fail(function (response) {
                    self.closeModal(popupModal);
                    errorProcessor.process(response, self.messageContainer);
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                });
            },
            /**
             * Based on the response we can start a 3DS2 validation or place the order
             * @param responseJSON
             */
            handleAdyenResult: function (responseJSON, orderId) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the success page
                    window.location.replace(url.build(
                        window.checkoutConfig.payment[quote.paymentMethod().method].successPage,
                    ));
                } else {
                    // Handle action
                    self.handleAction(response.action, orderId);
                }
            },
            handleAction: function (action, orderId) {
                var self = this;

                let popupModal;
                let actionContainer;

                fullScreenLoader.stopLoader();

                if (action.type === 'threeDS2' || action.type === 'await') {
                    popupModal = self.showModal();
                    actionContainer = '#cc_actionContainer';
                } else {
                    actionContainer = '#oneclick_actionContainer';
                }
                try {
                    this.checkoutComponent.createFromAction(
                        action).mount(actionContainer);
                } catch (e) {
                    console.log(e);
                    self.closeModal(popupModal);
                }
            },
            /**
             * List all Adyen billing agreements
             * Set up installments
             *
             * @returns {Array}
             */
            getAdyenBillingAgreements: function () {
                var self = this;

                // convert to list so you can iterate
                var paymentList = _.map(
                    window.checkoutConfig.payment.adyenOneclick.billingAgreements,
                    function (billingAgreement) {

                        var creditCardExpMonth, creditCardExpYear = false;

                        if (billingAgreement.agreement_data.card) {
                            creditCardExpMonth = billingAgreement.agreement_data.card.expiryMonth;
                            creditCardExpYear = billingAgreement.agreement_data.card.expiryYear;
                        }

                        // pre-define installments if they are set
                        var i, installments = [];
                        var grandTotal = quote.totals().grand_total;
                        var dividedString = '';
                        var dividedAmount = 0;

                        if (billingAgreement.number_of_installments) {
                            for (i = 0; i <
                            billingAgreement.number_of_installments.length; i++) {
                                dividedAmount = (grandTotal /
                                    billingAgreement.number_of_installments[i]).toFixed(
                                    quote.getPriceFormat().precision);
                                dividedString = billingAgreement.number_of_installments[i] +
                                    ' x ' +
                                    dividedAmount + ' ' +
                                    quote.totals().quote_currency_code;

                                installments.push({
                                    key: [dividedString],
                                    value: billingAgreement.number_of_installments[i],
                                });
                            }
                        }

                        var messageContainer = self.messageComponents['messages-' +
                        billingAgreement.reference_id];

                        // for recurring enable the placeOrder button at all times
                        var placeOrderAllowed = true;
                        if (self.hasVerification()) {
                            placeOrderAllowed = false;
                        } else {
                            // for recurring cards there is no validation needed
                            isValid(true);
                        }

                        return {
                            'label': billingAgreement.agreement_label,
                            'value': billingAgreement.reference_id,
                            'agreement_data': billingAgreement.agreement_data,
                            'logo': billingAgreement.logo,
                            'installment': '',
                            'number_of_installments': billingAgreement.number_of_installments,
                            'method': self.item.method,
                            'creditCardExpMonth': ko.observable(
                                creditCardExpMonth),
                            'creditCardExpYear': ko.observable(
                                creditCardExpYear),
                            'getInstallments': ko.observableArray(installments),
                            'placeOrderAllowed': ko.observable(
                                placeOrderAllowed),

                            isButtonActive: function () {
                                return self.isActive() && this.getCode() ==
                                    self.isChecked() &&
                                    self.isBillingAgreementChecked() &&
                                    this.placeOrderAllowed() &&
                                    self.isPlaceOrderActionAllowed();
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
                            placeOrder: function (data, event) {
                                var innerSelf = this;

                                if (event) {
                                    event.preventDefault();
                                }
                                // only use installments for cards
                                if (this.agreement_data.card) {
                                    if (this.hasVerification()) {
                                        var options = {enableValidations: false};
                                    }
                                    numberOfInstallments(this.installment);
                                }

                                if (this.validate() &&
                                    additionalValidators.validate()) {
                                    fullScreenLoader.startLoader();
                                    this.isPlaceOrderActionAllowed(false);

                                    this.getPlaceOrderDeferredObject().fail(
                                        function () {
                                            fullScreenLoader.stopLoader();
                                            innerSelf.isPlaceOrderActionAllowed(
                                                true);
                                        },
                                    ).done(
                                        function (orderId) {
                                            innerSelf.afterPlaceOrder();

                                            self.orderId = orderId;
                                            adyenPaymentService.getOrderPaymentStatus(
                                                orderId).done(function (responseJSON) {
                                                self.handleAdyenResult(
                                                    responseJSON,
                                                    orderId);
                                            });
                                        },
                                    );
                                }
                                return false;
                            },

                            /**
                             * Renders the secure CVC field,
                             * creates the card component,
                             * sets up the callbacks for card components
                             */
                            renderSecureCVC: function () {
                                if (!this.getClientKey()) {
                                    return;
                                }

                                var hideCVC = false;
                                // hide cvc if contract has been stored as recurring
                                if (!this.hasVerification()) {
                                    hideCVC = true;
                                }

                                try {
                                    this.component = self.checkoutComponent.create(
                                        'card', {
                                            hideCVC: hideCVC,
                                            brand: this.agreement_data.variant,
                                            storedPaymentMethodId: this.value,
                                            expiryMonth: this.agreement_data.card.expiryMonth,
                                            expiryYear: this.agreement_data.card.expiryYear,
                                            holderName: this.agreement_data.card.holderName,
                                            onChange: this.handleOnChange.bind(this)
                                        }).mount('#cvcContainer-' + this.value);
                                } catch (err) {
                                    console.log(err);
                                    // The component does not exist yet
                                }

                            },
                            handleOnChange: function (state, component) {
                                this.placeOrderAllowed(
                                    !!state.isValid);
                                isValid(!!state.isValid);
                            },
                            /**
                             * Builds the payment details part of the payment information request
                             *
                             * @returns {{method: *, additional_data: {variant: *, recurring_detail_reference: *, number_of_installments: *, cvc: (string|*), expiryMonth: *, expiryYear: *}}}
                             */
                            getData: function () {
                                const self = this;

                                let stateData;
                                if ('component' in self) {
                                    stateData = self.component.data;
                                }
                                stateData = JSON.stringify(stateData);
                                window.sessionStorage.setItem('adyen.stateData', stateData);
                                return {
                                    'method': self.method,
                                    additional_data: {
                                        number_of_installments: numberOfInstallments(),
                                        stateData: stateData,
                                    },
                                };
                            },
                            validate: function () {

                                var code = self.item.method;
                                var value = this.value;
                                var codeValue = code + '_' + value;

                                var form = 'form[data-role=' + codeValue + ']';

                                var validate = $(form).validation() &&
                                    $(form).validation('isValid');

                                // bcmc does not have any cvc
                                if (!validate ||
                                    (isValid() == false && variant() !=
                                        'bcmc' && variant() !=
                                        'maestro')) {
                                    return false;
                                }

                                return true;
                            },
                            getCode: function () {
                                return self.item.method;
                            },
                            hasVerification: function () {
                                return self.hasVerification();
                            },
                            getMessageName: function () {
                                return 'messages-' +
                                    billingAgreement.reference_id;
                            },
                            getMessageContainer: function () {
                                return messageContainer;
                            },
                            getClientKey: function () {
                                return adyenConfiguration.getClientKey();
                            },
                            isPlaceOrderActionAllowed: function () {
                                return self.isPlaceOrderActionAllowed(); // needed for placeOrder method
                            },
                            afterPlaceOrder: function () {
                                return self.afterPlaceOrder(); // needed for placeOrder method
                            },
                            getPlaceOrderDeferredObject: function () {
                                return $.when(
                                    placeOrderAction(this.getData(),
                                        this.getMessageContainer()),
                                );
                            },
                        };
                    });

                return paymentList;
            },
            /**
             * Select a billing agreement (stored one click payment method) from the list
             *
             * @returns {boolean}
             */
            selectBillingAgreement: function () {
                var self = this;

                // set payment method data
                var data = {
                    'method': self.method,
                    'po_number': null,
                    'additional_data': {
                        recurring_detail_reference: self.value,
                    },
                };

                // set the brandCode
                recurringDetailReference(self.value);
                variant(self.agreement_data.variant);

                // set payment method
                paymentMethod(self.method);

                selectPaymentMethodAction(data);
                checkoutData.setSelectedPaymentMethod(self.method);

                return true;
            },

            isBillingAgreementChecked: ko.computed(function () {

                if (!quote.paymentMethod()) {
                    return null;
                }

                if (quote.paymentMethod().method == paymentMethod()) {
                    return recurringDetailReference();
                }
                return null;
            }),

            getPlaceOrderUrl: function () {
                return window.checkoutConfig.payment.iframe.placeOrderUrl[this.getCode()];
            },
            hasVerification: function () {
                return window.checkoutConfig.payment.adyenOneclick.hasCustomerInteraction;
            },

            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },
            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },
            getCode: function () {
                return window.checkoutConfig.payment.adyenOneclick.methodCode;
            },
            isActive: function () {
                return true;
            },
            getControllerName: function () {
                return window.checkoutConfig.payment.iframe.controllerName[this.getCode()];
            },
            context: function () {
                return this;
            },
            isShowLegend: function () {
                return true;
            },
            showModal: function () {
                let popupModal = $('#cc_actionModal').modal({
                    // disable user to hide popup
                    clickableOverlay: false,
                    responsive: true,
                    innerScroll: false,
                    // empty buttons, we don't need that
                    buttons: [],
                    modalClass: 'cc_actionModal',
                });

                popupModal.modal('openModal');

                return popupModal;
            }, closeModal: function (popupModal) {
                popupModal.modal('closeModal');
                $('.cc_actionModal').remove();
                $('.oneclick_actionModal').remove();
                $('.modals-overlay').remove();
                $('body').removeClass('_has-modal');

                // reconstruct the threeDS2Modal container again otherwise component can not find the threeDS2Modal
                $('#cc_actionModalWrapper').append('<div id="cc_actionModal">' +
                    '<div id="cc_actionContainer"></div>' +
                    '</div>');
                $('#oneclick_actionModalWrapper').append('<div id="oneclick_actionModal">' +
                    '<div id="oneclick_actionContainer"></div>' +
                    '</div>');
            }
        });
    },
);
