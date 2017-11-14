/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    paths: {
        'adyen/encrypt' : 'Adyen_Payment/js/view/payment/adyen.encrypt.min'
    },
    config: {
        mixins: {
            'Adyen_Payment/js/action/place-order': {
                'Magento_CheckoutAgreements/js/model/place-order-mixin': true
            }
        }
    }
};
