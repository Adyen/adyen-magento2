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
        'Adyen_Payment/js/adyen',
        'mage/translate'
    ],
    function(
        $,
        ko,
        Component,
        fullScreenLoader,
        placeOrderAction,
        adyenPaymentService,
        adyenConfiguration,
        adyenCheckout,
        $t
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/giftcard-form'
            },
            giftcardPaymentMethods: ko.observable(null),
            selectedGiftcard: ko.observable(null),
            redeemedCards: ko.observableArray(),
            totalGiftcardBalance: ko.observable(),
            canAddNewGiftCard: ko.observable(true),
            showRemoveSingleGiftcardButton: ko.observable(false),
            showAvailableGiftcardPaymentMethods: ko.observable(false),
            showPlaceOrderButton: ko.observable(false),
            selectedGiftcardPaymentMethod: ko.observable(null),
            giftcardTitle: ko.observable(null),

            initialize: async function () {
                this._super();
                let self = this;

                var paymentMethodsObserver = adyenPaymentService.getPaymentMethods();
                paymentMethodsObserver.subscribe(
                    function(paymentMethodsResponse) {
                        self.fetchGiftcardPaymentMethods(paymentMethodsResponse);
                    }
                );

                await this.fetchRedeemedGiftcards();
            },

            addNewGiftcard: function () {
                this.canAddNewGiftCard(false);
                this.showAvailableGiftcardPaymentMethods(true);
            },

            removeSingleGiftcard: function () {
                this.removeCheckoutComponent();
                this.canAddNewGiftCard(true);
                this.showPlaceOrderButton(false);
                this.showRemoveSingleGiftcardButton(false);
                this.giftcardTitle(null);
            },

            fetchGiftcardPaymentMethods: function (paymentMethodsResponse) {
                fullScreenLoader.startLoader();
                let giftcards = [];

                let paymentMethods = paymentMethodsResponse.paymentMethodsResponse.paymentMethods;

                if (!!paymentMethods && paymentMethods.length > 0) {
                    giftcards.push({
                        key: $t('Please select a giftcard...'),
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

                    this.giftcardPaymentMethods(giftcards);
                }

                fullScreenLoader.stopLoader();
            },

            giftcardOnSelect: async function (obj, event, test) {
                let selectedValue = event.target.value;

                if (selectedValue !== "") {
                    fullScreenLoader.startLoader();

                    this.showAvailableGiftcardPaymentMethods(false);
                    // add giftcard title to component wrapper
                    let selectedGiftcard = this.giftcardPaymentMethods().find(function (paymentMethod) {
                        return paymentMethod.value === selectedValue;
                    });

                    this.selectedGiftcard(selectedGiftcard);

                    if (typeof this.adyenCheckoutComponent === 'undefined') {
                        this.adyenCheckout = await this.initiateAdyenCheckout();
                    }

                    this.mountGiftcardComponent();
                    this.showRemoveSingleGiftcardButton(true);

                    $('#adyen_giftcard_giftcard_payment_methods').val('');

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
                    brand: this.selectedGiftcard().value,
                    showPayButton: true,
                    onBalanceCheck: function (resolve, reject, data) {
                        adyenPaymentService.paymentMethodsBalance(data)
                            .done(function (balanceResponse) {
                                let response = JSON.parse(balanceResponse);
                                self.handleBalanceResult(response, data, resolve);
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

            removeGiftcard: function (data, event) {
                let self = this;
                fullScreenLoader.startLoader();
                adyenPaymentService.removeStateData(data.stateDataId).done(function () {
                    self.fetchRedeemedGiftcards();
                    fullScreenLoader.stopLoader();
                });
            },

            handleBalanceResult: function (balanceResponse, stateData, resolve) {
                let self = this;
                let orderAmount = window.checkoutConfig.payment.adyenGiftcard.amount;

                if(this.totalGiftcardBalance() === 0 && balanceResponse.balance.value >= orderAmount) {
                    resolve(balanceResponse);
                } else if (orderAmount > this.totalGiftcardBalance()) {
                    stateData.giftcard = {
                        balance: balanceResponse.balance,
                        title: this.selectedGiftcard().key
                    }
                    adyenPaymentService.saveStateData(stateData).done(function () {
                        self.removeCheckoutComponent();
                        self.giftcardTitle(null);
                        self.showRemoveSingleGiftcardButton(false);
                        // Update the list of the redeemed giftcards and giftcard balance total
                        self.fetchRedeemedGiftcards();
                    });
                }
            },

            fetchRedeemedGiftcards: function () {
                let self = this;
                let orderAmount = window.checkoutConfig.payment.adyenGiftcard.amount;

                adyenPaymentService.fetchRedeemedGiftcards().done(function (giftcards) {
                    giftcards = JSON.parse(giftcards);
                    let totalBalance = 0;

                    $.each(giftcards, function (index, item) {
                        totalBalance += item.balance.value;
                    });

                    self.totalGiftcardBalance(totalBalance);
                    self.redeemedCards(giftcards);

                    // Compare the new total giftcard balance with the order amount
                    if (orderAmount > self.totalGiftcardBalance()) {
                        self.canAddNewGiftCard(true);
                        self.showPlaceOrderButton(false);
                    } else {
                        // initiate place order button of magento checkout / not the pay button of the component
                        self.canAddNewGiftCard(false);
                        self.showPlaceOrderButton(true);
                    }
                });
            },

            removeCheckoutComponent: function () {
                if (typeof this.adyenCheckout !== 'undefined') {
                    this.adyenCheckout.remove(this.giftcardComponent);
                }
            },

            mountGiftcardComponent: function () {
                if (typeof this.giftcardComponent !== 'undefined') {
                    this.adyenCheckout.remove(this.giftcardComponent);
                }

                let giftcardConfiguration = this.getGiftcardComponentConfiguration();
                this.giftcardComponent = this.adyenCheckout
                    .create('giftcard', giftcardConfiguration)
                    .mount('#giftcard-component-wrapper');

                this.giftcardTitle(this.selectedGiftcard().key);
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

            isActive: function() {
                return false;
            },
        });
    }
);
