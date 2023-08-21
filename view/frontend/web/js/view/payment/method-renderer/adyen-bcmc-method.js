/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
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
        adyenPaymentMethod,
    ) {
        return adyenPaymentMethod.extend({
            txVariant: 'bcmc_mobile',
            initialize: function () {
                this._super();
            }
        })
    }
);
