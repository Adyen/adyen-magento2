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
        'Magento_Checkout/js/view/summary/abstract-total'
    ],
    function(
        Component
    ) {
        "use strict";
        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/checkout/summary/giftcard-summary'
            },

            getGiftcardDiscount: function() {
                return window.checkoutConfig.payment.adyen.giftcard.totalDiscount;
            },

            getRemainingAmount: function () {
                return window.checkoutConfig.payment.adyen.giftcard.remainingOrderAmount;
            },

            showGiftcardSummary: function () {
                return window.checkoutConfig.payment.adyen.giftcard.isRedeemed;
            }
        });
    }
);
