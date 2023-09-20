/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
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

        const paymentMethodComponent = 'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method';
        const adyenTxVariants = window.checkoutConfig.payment.adyen.txVariants;
        const customMethodRenderers = window.checkoutConfig.payment.adyen.customMethodRenderers;

        // Push payment method renderers for alternative payment methods
        Object.keys(adyenTxVariants).forEach((index) => {
            rendererList.push({
                type: index,
                component: paymentMethodComponent
            });
        });

        // Push custom payment method renderers
        Object.keys(customMethodRenderers).forEach((index) => {
            rendererList.push({
                type: index,
                component: customMethodRenderers[index]
            });
        });

        /** Add view logic here if needed */
        return Component.extend({
            initialize: function () {
                this._super();
            }
        });
    }
);
