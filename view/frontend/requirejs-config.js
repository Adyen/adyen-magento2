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
            'Magento_CheckoutAgreements/js/model/agreements-assigner': {
                'Adyen_Payment/js/view/checkout/summary/agreements-assigner-minix': true
            }
        }
    }
};