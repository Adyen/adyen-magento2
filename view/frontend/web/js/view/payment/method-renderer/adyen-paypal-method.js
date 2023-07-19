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
    ],
    function(
        quote,
        adyenPaymentMethod,
        fullScreenLoader
    ) {
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            txVariant: 'paypal',
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();
                let paypalConfiguration = Object.assign(baseComponentConfiguration.showPayButton=true, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                return paypalConfiguration
            },
            renderActionComponent: function(resultCode, action, component) {
                var self = this;
                var actionNode = document.getElementById(this.modalLabel + 'Content');
                fullScreenLoader.stopLoader();

                self.actionComponent = component.handleAction(action);
            },
        })
    }
);
