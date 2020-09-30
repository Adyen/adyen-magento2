/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'Magento_Checkout/js/model/url-builder',
        'mage/storage'
    ],
    function (urlBuilder, storage) {
        'use strict';
        return {
            /**
             * The results that the components returns in the onComplete callback needs to be sent to the
             * backend to the /adyen/paymentDetails endpoint and based on the response render a new
             * component or place the order (validateThreeDS2OrPlaceOrder)
             * @param response
             */
            process: function (data) {
                var payload = {
                    "payload": JSON.stringify(data)
                };

                var serviceUrl = urlBuilder.createUrl('/adyen/paymentDetails', {});

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    true
                );
            }
        };
    }
);
