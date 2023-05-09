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
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/adyen'
    ],
    function(
        $,
        ko,
        Component,
        fullScreenLoader,
        placeOrderAction,
        adyenPaymentService,
        adyenConfiguration,
        adyenCheckout
    ) {
        'use strict';
        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/giftcard-form'
            },
            giftcardPaymentMethods: ko.observable(null),
            selectedGiftcard: ko.observable(null),
            redeemedCards: ko.observableArray(),
            totalGiftcardBalance: ko.observable(0),
            canAddNewGiftCard: ko.observable(true),
            showAvailableGiftcardPaymentMethods: ko.observable(false),

            initialize: function () {
                this._super();
                let self = this;

                var paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
                paymentMethodsObserver.subscribe(
                    function(paymentMethodsResponse) {
                        self.fetchGiftcardPaymentMethods(paymentMethodsResponse);
                    }
                );

                this.fetchRedeemedGiftcards();
            },

            addNewGiftcard: function () {
                this.canAddNewGiftCard(false);
                this.showAvailableGiftcardPaymentMethods(true);
            },

            fetchGiftcardPaymentMethods: function (paymentMethodsResponse) {
                fullScreenLoader.startLoader();
                let giftcards = [];

                let paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;

                if (!!paymentMethods && paymentMethods.length > 0) {
                    giftcards.push({
                        key: 'Select something placeholder...',
                        value: ''
                    });

                    $.each(paymentMethods, function (index, paymentMethod) {
                        if (paymentMethod.type === 'giftcard') {
                            giftcards.push({
                                key: paymentMethod.name,
                                value: paymentMethod.brand
                            });
                        }
                    });
                }

                if (giftcards.length < 1) {
                    // deactivate the payment method and return
                }

                this.giftcardPaymentMethods(giftcards);
                fullScreenLoader.stopLoader();
            },

            giftcardOnselect: async function (obj, event) {
                let selectedGiftcard = event.target.value;

                if (selectedGiftcard !== "") {
                    fullScreenLoader.startLoader();

                    this.showAvailableGiftcardPaymentMethods(false);
                    // add giftcard title to component wrapper
                    this.selectedGiftcard(selectedGiftcard);

                    if (typeof this.adyenCheckoutComponent === 'undefined') {
                        this.adyenCheckout = await this.initiateAdyenCheckout();
                    }

                    this.mountGiftcardComponent();
                    fullScreenLoader.stopLoader();
                }
            },

            initiateAdyenCheckout: function () {
                let remainingBalance = window.checkoutConfig.payment.adyenGiftcard.amount - this.totalGiftcardBalance();

                let adyenCheckoutConfiguration = {
                    locale: adyenConfiguration.getLocale(),
                    clientKey: adyenConfiguration.getClientKey(),
                    environment: adyenConfiguration.getCheckoutEnvironment(),
                    amount: {
                        currency: window.checkoutConfig.payment.adyenGiftcard.currency,
                        value: (remainingBalance < 0) ? 0 : remainingBalance
                    }
                };

                return adyenCheckout(adyenCheckoutConfiguration);
            },

            getGiftcardComponentConfiguration: function () {
                let self = this;
                return {
                    brand: this.selectedGiftcard(),
                    showPayButton: true,
                    onBalanceCheck: function (resolve, reject, data) {
                        adyenPaymentService.paymentMethodsBalance(data)
                            .done(function (balanceResponse) {
                                let response = JSON.parse(balanceResponse);
                                resolve(response);
                                self.handleBalanceResult(response, data);
                            })
                            .fail(function () {
                                reject();
                                console.log('Balance check failed!');
                            });
                    },
                    onSubmit: function (data) {
                        self.placeOrder(data);
                    }
                };
            },

            handleBalanceResult: function (balanceResponse, stateData) {
                let orderAmount = window.checkoutConfig.payment.adyenGiftcard.amount;

                if(this.totalGiftcardBalance() === 0 && balanceResponse.balance.value >= orderAmount) {

                } else if (orderAmount > this.totalGiftcardBalance()) {
                    stateData.balance = balanceResponse.balance;
                    adyenPaymentService.saveStateData(stateData);

                    let redeemedGiftcards = this.redeemedCards();
                    stateData.stateDataId = 1;
                    redeemedGiftcards.push(stateData);
                    this.redeemedCards(redeemedGiftcards);

                    // Update the list of the redeemed giftcards and giftcard balance total
                    this.fetchRedeemedGiftcards();

                    // Compare the new total giftcard balance with the order amount
                    if (orderAmount > this.totalGiftcardBalance()) {
                        this.adyenCheckout.remove(this.giftcardComponent);
                        this.canAddNewGiftCard(true);
                    }
                }
            },

            fetchRedeemedGiftcards: function () {
                let totalBalance = 0;

                $.each(this.redeemedCards(), function (index, item) {
                    totalBalance += item.balance.value;
                });

                this.totalGiftcardBalance(totalBalance);
            },

            mountGiftcardComponent: function () {
                if (typeof this.giftcardComponent !== 'undefined') {
                    this.adyenCheckout.remove(this.giftcardComponent);
                }

                let giftcardConfiguration = this.getGiftcardComponentConfiguration();
                this.giftcardComponent = this.adyenCheckout.create('giftcard', giftcardConfiguration).mount('#giftcard-component-wrapper');
            },

            placeOrder: async function(stateData) {
                var self = this;

                let additionalData = {};
                additionalData.brand_code = 'genericgiftcard';
                additionalData.stateData = JSON.stringify(stateData.data);

                let data = {
                    'method': this.item.method,
                    'additional_data': additionalData
                };

                await $.when(placeOrderAction(data, self.currentMessageContainer)).fail(
                    function(response) {
                        console.log(response);
                    }
                ).done(
                    function(orderId) {
                        self.afterPlaceOrder();
                        adyenPaymentService.getOrderPaymentStatus(orderId).done(function(responseJSON) {
                            self.validateActionOrPlaceOrder(responseJSON, orderId);
                        });
                    }
                );
            },

            validateActionOrPlaceOrder: function(responseJSON, orderId) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the success page
                    $.mage.redirect(
                        window.checkoutConfig.payment.adyen.successPage
                    );
                } else {
                    // render component
                    self.orderId = orderId;
                    console.log("action required!");
                }
            },
        });
    }
);
