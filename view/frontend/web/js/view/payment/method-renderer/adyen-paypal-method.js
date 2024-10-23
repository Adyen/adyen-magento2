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
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
        'Magento_Checkout/js/model/full-screen-loader',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/error-processor',
    ],
    function(
        quote,
        adyenPaymentMethod,
        fullScreenLoader,
        adyenPaymentService,
        errorProcessor
    ) {
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();
                let paypalConfiguration = Object.assign(baseComponentConfiguration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                paypalConfiguration.showPayButton = true;

                return paypalConfiguration
            },
            renderActionComponent: function(resultCode, action, component) {
                fullScreenLoader.stopLoader();
                   this.token = action.sdkData.token;
                this.actionComponent = component.handleAction(action);
            },
            handleOnFailure: function(response, component) {
                this.isPlaceOrderAllowed(true);
                fullScreenLoader.stopLoader();
                component.handleReject(response);
            },
            handleOnError:  function (error, component) {
                if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                    console.log("onError:",error);
                }

                // call endpoint with component.paymentData if available
                let request = {};
                if (!!component.paymentData) {
                    request.paymentData = component.paymentData;
                }
                //Create details array for the payload
                let details ={};
                if(!!this.token) {
                    details.orderID= this.token;
                }
                request.details = details;
                request.cancelled = true;

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
                        this.currentMessageContainer);
                    this.isPlaceOrderAllowed(true);
                    this.showErrorMessage(response);
                });
            }
        })
    }
);
