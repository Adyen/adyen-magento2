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
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
        'Magento_Checkout/js/model/full-screen-loader',
    ],
    function(
        adyenPaymentMethod,
        fullScreenLoader
    ) {
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();
                let cashAppConfiguration = Object.assign(baseComponentConfiguration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                cashAppConfiguration.showPayButton = true;
                cashAppConfiguration.enableStoreDetails = true;
                return cashAppConfiguration
            },
            renderActionComponent: function(resultCode, action, component) {
                fullScreenLoader.stopLoader();

                this.actionComponent = component.handleAction(action);
            },
        })
    }
);
