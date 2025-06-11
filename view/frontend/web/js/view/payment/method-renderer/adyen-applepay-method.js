/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
    ],
    function(
        quote,
        adyenPaymentMethodComponent,
    ) {
        return adyenPaymentMethodComponent.extend({
            placeOrderButtonVisible: false,
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();
                let self = this;
                let applePayConfiguration = Object.assign(baseComponentConfiguration,
                    {
                        showPayButton: true,
                        totalPriceLabel: baseComponentConfiguration.configuration.merchantName,
                        amount: paymentMethodsExtraInfo[paymentMethod.type].configuration.amount
                    }
                );
                applePayConfiguration.onClick = function(resolve, reject) {
                    if (self.validate()) {
                        resolve();
                    } else {
                        reject();
                    }
                }

                return applePayConfiguration;
            }
        })
    }
);
