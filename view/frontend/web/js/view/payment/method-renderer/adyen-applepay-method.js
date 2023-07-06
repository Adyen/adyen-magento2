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
            initialize: function () {
                this._super();

                this.adyenPaymentMethod(this.checkBrowserCompatibility());
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo, result) {
                let baseComponentConfiguration = this._super();
                let applePayConfiguration = Object.assign(baseComponentConfiguration,
                    {
                        showPayButton: true,
                        totalPriceLabel: baseComponentConfiguration.configuration.merchantName,
                        amount: paymentMethodsExtraInfo[paymentMethod.methodIdentifier].configuration.amount
                    }
                );

                return applePayConfiguration;
            },
            checkBrowserCompatibility: function () {
                // Disables Apple Pay for non-Safari browsers
                return /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            }
        })
    }
);
