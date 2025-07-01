/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'jquery',
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
        'Adyen_Payment/js/model/adyen-configuration',
        'Magento_Checkout/js/model/full-screen-loader',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/error-processor',
        'Adyen_Payment/js/model/payment-component-states',
    ],
    function(
        $,
        adyenPaymentMethod,
        adyenConfiguration,
        fullScreenLoader,
        adyenPaymentService,
        errorProcessor,
        paymentComponentStates
    ) {
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            token: null,
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let self = this;

                let baseComponentConfiguration = this._super();
                let paypalConfiguration = Object.assign(baseComponentConfiguration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                paypalConfiguration.showPayButton = true;
                paypalConfiguration.cspNonce = adyenConfiguration.getCspNonce();
paypalConfiguration.onSubmit = this.handleOnSubmit.bind(this);

                let agreementsConfig = adyenConfiguration.getAgreementsConfig();

                if (agreementsConfig && agreementsConfig.checkoutAgreements.isEnabled) {
                    let agreementsMode = null;
                    agreementsConfig.checkoutAgreements.agreements.forEach((item) => {
                        if (item.mode === '1') {
                            agreementsMode = 'manual';
                        }
                    });

                    if (agreementsMode === 'manual') {
                        paypalConfiguration.onInit = function (data, actions) {
                            try {
                                actions.disable();

                                $("input.required-entry").on('change', function () {
                                    self.validate() ? actions.enable() : actions.disable();
                                });
                            } catch (error) {
                                console.warn("PayPal component initialization failed!");
                            }
                        };

                        paypalConfiguration.onClick = function (data, actions) {
                            if (self.validate()) {
                                return actions.resolve();
                            } else {
                                return actions.reject();
                            }
                        };
                    }
                }

                return paypalConfiguration;
            },
            renderActionComponent: function(resultCode, action, component) {
                fullScreenLoader.stopLoader();
                this.token = action.sdkData.token;
                this.actionComponent = component.handleAction(action);
            },
            handleOnFailure: function(response, component) {
                this.isPlaceOrderAllowed(true);
                fullScreenLoader.stopLoader();
                if (response && response.error) {
                    console.error('Error details:', response.error);
                }
                component.handleReject(response);
            },
            handleOnError:  function (error, component) {
                let self = this;
                if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                    console.log("An error occured on PayPal component!");
                }

                // call endpoint with component.paymentData if available
                let request = {};
                if (!!component.paymentData) {
                    request.paymentData = component.paymentData;
                }

                //Create details array for the payload
                let details = {};
                if(!!this.token) {
                    details.orderID = this.token;
                }
                request.details = details;

                adyenPaymentService.paymentDetails(request, this.orderId).done(function() {
                    $.mage.redirect(
                        window.checkoutConfig.payment.adyen.successPage
                    );
                }).fail(function(response) {
                    fullScreenLoader.stopLoader();

                    if (this.popupModal) {
                        this.closeModal(this.popupModal);
                    }
                    errorProcessor.process(response,
                        self.currentMessageContainer);
                    paymentComponentStates().setIsPlaceOrderAllowed(self.getMethodCode(), true);
                });
            }
        })
    }
);
