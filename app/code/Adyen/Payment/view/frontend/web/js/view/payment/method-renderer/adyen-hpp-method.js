/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Adyen_Payment/js/action/set-payment-method'
    ],
    function ($, Component, setPaymentMethodAction) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/payment/hpp-form'
            },
            /** Redirect to adyen */
            continueToAdyen: function () {
                //update payment method information if additional data was changed
                this.selectPaymentMethod();
                setPaymentMethodAction();
                return false;
            }
        });
    }
);
