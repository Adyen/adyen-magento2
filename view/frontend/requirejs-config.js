/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    config: {
        mixins: {
            'Magento_Tax/js/view/checkout/summary/grand-total': {
                'Adyen_Payment/js/view/checkout/summary/grand-total-mixin': true
            },
            'Magento_Checkout/js/action/set-shipping-information': {
              'Adyen_Payment/js/model/set-shipping-information-mixin': true
            },
            'Magento_Multishipping/js/payment': {
                'Adyen_Payment/js/view/checkout/multishipping/payment-mixin': true
             },
            'Magento_CheckoutAgreements/js/model/agreements-assigner': {
                'Adyen_Payment/js/view/checkout/summary/agreements-assigner-mixin': true
            }
        }
    }
};