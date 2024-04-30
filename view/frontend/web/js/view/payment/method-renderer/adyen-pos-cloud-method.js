/*
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
        'Adyen_Payment/js/model/installments',
        'Adyen_Payment/js/model/adyen-payment-service'
    ],
    function (ko,
              $,
              Component,
              additionalValidators,
              placeOrderAction,
              quote,
              agreementsAssigner,
              customer,
              urlBuilder,
              storage,
              fullScreenLoader,
              errorProcessor,
              Messages,
              redirectOnSuccessAction,
              installmentsHelper,
              adyenPaymentService
    ) {
        'use strict';

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/pos-cloud-form'
            },
            fundingSource: ko.observable('credit'),
            initObservable: function () {
                this._super()
                    .observe([
                        'terminalId',
                        'fundingSource',
                        'installments',
                        'installment'
                    ]);

                return this;
            },
            initialize: function () {
                this._super();
                let self = this;

                // installments
                let allInstallments = self.getAllInstallments();
                let grandTotal = self.grandTotal();
                let precision = quote.getPriceFormat().precision;
                let currencyCode = quote.totals().quote_currency_code;

                let numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(allInstallments, grandTotal, precision, currencyCode);

                if (numberOfInstallments) {
                    self.installments(numberOfInstallments);
                } else {
                    self.installments(0);
                }
            },
            posComplete: function () {
                this.afterPlaceOrder();
                if (this.redirectAfterPlaceOrder) {
                    redirectOnSuccessAction.execute();
                }
            },
            placeOrderPos: function () {
                let self = this;
                fullScreenLoader.startLoader();
                placeOrderAction(self.getData(), new Messages())
                    .fail(function (response) {
                        self.handleFailedResponse(response)
                    })
                    .done(function (orderId) {
                        let posPaymentAction = window.checkoutConfig.payment.adyenPos.paymentAction;
                        if (posPaymentAction === 'order') {
                            adyenPaymentService.posPayment(orderId)
                                .fail(function (response) {
                                    self.handleFailedResponse(response)
                                })
                                .done(function () {
                                    self.posComplete()
                                });
                        } else {
                            self.posComplete();
                        }
                    })
            },
            handleFailedResponse: function (response) {
                let self = this;
                if (response.responseText.indexOf("In Progress") > -1) {
                    window.setTimeout(function () {
                        this.placeOrderPos()
                    }, 5000);
                    return;
                }
                errorProcessor.process(response);
                fullScreenLoader.stopLoader();
                self.isPlaceOrderActionAllowed(true);
            },
            getConnectedTerminals: function () {
                let connectedTerminals = [];
                let connectedTerminalsList = adyenPaymentService.getConnectedTerminals();

                if (!!connectedTerminalsList()) {
                    for (let terminal of connectedTerminalsList()) {
                        connectedTerminals.push(
                            {
                                key: terminal,
                                value: terminal
                            }
                        );
                    }
                }

                return connectedTerminals;
            },
            isFundingSourceAvailable: function () {
                if (quote.billingAddress() === null) {
                    return false;
                }
                let countryId = quote.billingAddress().countryId;
                let currencyCode = quote.totals().quote_currency_code;
                let allowedCurrenciesByCountry = {
                    'BR': 'BRL',
                    'MX': 'MXN',
                };
                return allowedCurrenciesByCountry[countryId] &&
                    currencyCode === allowedCurrenciesByCountry[countryId];
            },
            getFundingSourceOptions: function () {
                let fundingSource = [];
                const fundingSourceOptions = window.checkoutConfig.payment.adyenPos.fundingSourceOptions;

                for (let i = 0; i < Object.values(fundingSourceOptions).length; i++) {
                    fundingSource.push(
                        {
                            value:  Object.keys(fundingSourceOptions)[i],
                            key: Object.values(fundingSourceOptions)[i]
                        }
                    );
                }

                return fundingSource;
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
                        'funding_source': this.fundingSource()
                    }
                };
            },
            hasInstallments: function () {
                return window.checkoutConfig.payment.adyenPos.hasInstallments && this.fundingSource() === 'credit';
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
            grandTotal: function () {
                for (const totalsegment of quote.getTotals()()['total_segments']) {
                    if (totalsegment.code === 'grand_total') {
                        return totalsegment.value;
                    }
                }
                return quote.totals().grand_total;
            },
        });
    }
);
