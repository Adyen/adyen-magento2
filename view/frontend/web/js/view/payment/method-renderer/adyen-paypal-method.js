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
        'Adyen_Payment/js/model/adyen-configuration',
        'Magento_Checkout/js/model/full-screen-loader',
        'jquery'
    ],
    function(
        quote,
        adyenPaymentMethod,
        adyenConfiguration,
        fullScreenLoader,
        $
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
                let agreementsConfig = adyenConfiguration.getAgreementsConfig();

                if (null !== agreementsConfig) {
                    let self = this;

                    paypalConfiguration.onInit = function (data, actions) {
                        actions.disable()

                        $(document).off('change', '.checkout-agreements input').on('change', '.checkout-agreements input', function () {
                            self.updatePayPalButton(actions);
                        });
                    }

                    paypalConfiguration.onClick = function (data, actions) {
                        if(!self.validate()) {
                            console.error('Agreements configuration failed');
                        }
                    }
                }

                return paypalConfiguration
            },
            renderActionComponent: function(resultCode, action, component) {
                fullScreenLoader.stopLoader();

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
            updatePayPalButton: function (actions) {
                if (this.validate()) {
                    actions.enable();
                } else {
                    actions.disable();
                }
            },
         })
    }
);
