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
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function (ko, $, Component, additionalValidators, placeOrderAction, quote, agreementsAssigner, customer, urlBuilder, storage, fullScreenLoader, errorProcessor, Messages, redirectOnSuccessAction) {
        'use strict';

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/pos-cloud-form'
            },

            initiate: function () {
                var self = this,
                    serviceUrl,
                    paymentData = quote.paymentMethod();

                if (this.getConnectedTerminals().length > 0) {
                    this.isPlaceOrderActionAllowed(true);
                }

                // use core code to assign the agreement
                agreementsAssigner(paymentData);
                serviceUrl = urlBuilder.createUrl('/adyen/initiate', {});
                fullScreenLoader.startLoader();
                return storage.post(
                    serviceUrl
                ).always(function(){
                    self.placeOrderPos()});
                return false;
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'terminalId'
                    ]);

                return this;
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
                            window.setTimeout(function(){
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
            getConnectedTerminals: function() {
                let connectedTerminals = [];
                const connectedTerminalsList = window.checkoutConfig.payment.adyenPos.connectedTerminals;

                for (let i = 0; i < connectedTerminalsList.length; i++) {
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
                        'terminal_id': this.terminalId()
                    }
                };
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            validate: function () {
                return true;
            }
        });
    }
);
