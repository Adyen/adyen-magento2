/*
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
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'Magento_CheckoutAgreements/js/model/agreements-assigner',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Ui/js/model/messages',
        'Magento_Checkout/js/action/redirect-on-success',
        'Adyen_Payment/js/model/installments'
    ],
    function (ko, $, Component, additionalValidators, placeOrderAction, quote, agreementsAssigner, customer, urlBuilder, storage, fullScreenLoader, errorProcessor, Messages, redirectOnSuccessAction, installmentsHelper) {
        'use strict';

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/pos-cloud-form'
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'terminalId',
                        'installments',
                        'installment'
                    ]);

                return this;
            },
            initialize: function () {
                this._super();
                var self = this;

                // installments
                var allInstallments = self.getAllInstallments();
                var grandTotal = quote.totals().grand_total;
                var precision = quote.getPriceFormat().precision;
                var currencyCode = quote.totals().quote_currency_code;

                var numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(allInstallments, grandTotal, precision, currencyCode);

                if (numberOfInstallments) {
                    self.installments(numberOfInstallments);
                } else {
                    self.installments(0);
                }
            },
            initiate: function () {
                var self = this,
                    serviceUrl,
                    paymentData = quote.paymentMethod();

                // use core code to assign the agreement
                agreementsAssigner(paymentData);
                serviceUrl = urlBuilder.createUrl('/adyen/initiate', {});
                fullScreenLoader.startLoader();

                var payload = {
                    "payload": JSON.stringify({
                        terminal_id: self.terminalId(),
                        number_of_installments: self.installment()
                    })
                }

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                ).always(function () {
                    self.placeOrderPos()});
                return false;
            },

            posComplete: function () {
                this.afterPlaceOrder();
                if (this.redirectAfterPlaceOrder) {
                    redirectOnSuccessAction.execute();
                }
            },
            placeOrderPos: function () {
                var self = this;
                return $.when(
                    placeOrderAction(self.getData(), new Messages())
                ).fail(
                    function (response) {
                        if (response.responseText.indexOf("In Progress") > -1) {
                            window.setTimeout(function () {
                                self.placeOrderPos()},5000);
                            return;
                        }
                        errorProcessor.process(response);
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                    }
                ).done(
                    function () {
                        self.posComplete();
                    }
                )
            },
            getConnectedTerminals: function () {
                var connectedTerminals = [];
                const connectedTerminalsList = window.checkoutConfig.payment.adyenPos.connectedTerminals;

                for (var i = 0; i < connectedTerminalsList.length; i++) {
                    connectedTerminals.push(
                        {
                            key: connectedTerminalsList[i],
                            value: connectedTerminalsList[i]
                        }
                    );
                }

                return connectedTerminals;
            },
            /**
             * Get data for place order
             * @returns {{method: *}}
             */
            getData: function () {
                return {
                    'method': this.item.method,
                    additional_data: {
                        'terminal_id': this.terminalId(),
                        'number_of_installments': this.installment(),
                    }
                };
            },
            hasInstallments: function () {
                return window.checkoutConfig.payment.adyenPos.hasInstallments;
            },
            getAllInstallments: function () {
                return window.checkoutConfig.payment.adyenPos.installments;
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            validate: function () {
                return true;
            },
            isActive: function () {
                return true;
            },
            /**
             * Returns state of place order button
             * @returns {boolean}
             */
            isButtonActive: function () {
                return this.isActive() && this.getCode() == this.isChecked() && this.getConnectedTerminals().length > 0 && this.validate();
            },
        });
    }
);
