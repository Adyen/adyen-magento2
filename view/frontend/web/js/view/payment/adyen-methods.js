/**
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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'adyen_oneclick',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-oneclick-method'
            },
            {
                type: 'adyen_cc',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-cc-method'
            },
            {
                type: 'adyen_hpp',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-hpp-method'
            },
            {
                type: 'adyen_boleto',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-boleto-method'
            },
            {
                type: 'adyen_apple_pay',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-apple-pay-method'
            },
            {
                type: 'adyen_pos_cloud',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-pos-cloud-method'
            },
            {
                type: 'adyen_google_pay',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-google-pay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({
            initialize: function () {
                var self = this;
                this._super();

                // include checkout card component javascript
                var checkoutCardComponentScriptTag = document.createElement('script');
                checkoutCardComponentScriptTag.id = "AdyenCheckoutCardComponentScript";
                checkoutCardComponentScriptTag.src = self.getCheckoutCardComponentSource();
                checkoutCardComponentScriptTag.type = "text/javascript";
                document.head.appendChild(checkoutCardComponentScriptTag);

                if (this.isGooglePayEnabled()) {
                    var googlepayscript = document.createElement('script');
                    googlepayscript.src = "https://pay.google.com/gp/p/js/pay.js";
                    googlepayscript.type = "text/javascript";
                    document.head.appendChild(googlepayscript);
                }
            },
            getCheckoutCardComponentSource: function() {
                return window.checkoutConfig.payment.checkoutCardComponentSource;
            },
            isGooglePayEnabled: function() {
                return window.checkoutConfig.payment.adyenGooglePay.active;
            }
        });
    }
);