/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    config: {
        mixins: {
            'Adyen_Payment/js/action/place-order': {
                'Magento_CheckoutAgreements/js/model/place-order-mixin': true
            }
        }
    },
    map: {
        '*': {
            'adyenCheckout':  'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/3.4.0/adyen.js',
            'adyenCheckout3101': 'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/3.10.1/adyen.js'
        }
    }
};
