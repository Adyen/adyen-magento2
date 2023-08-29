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
        'ko',
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Customer/js/customer-data',
        'mage/translate'
    ],
    function(
        ko,
        Component,
        customerData,
        $t
    ) {
        "use strict";
        const giftcardChangedEvent = 'Adyen_Payment_Event:giftcardChangedEvent';

        return Component.extend({
            defaults: {
                template: 'Adyen_Payment/checkout/summary/giftcard-summary'
            },

            giftcardDiscount: ko.observable(null),
            remainingAmount: ko.observable(null),
            showGiftcardSummary: ko.observable(null),

            initialize: function () {
                this._super();
                let self = this;

                customerData.get(giftcardChangedEvent).subscribe(function (response) {
                    let showSummaryBlock = response.redeemedGiftcards.length > 0;

                    if (showSummaryBlock) {
                        self.setGiftcardSummaryBlock(response.totalDiscount, response.remainingAmount, true)
                    } else {
                        self.resetGiftcardSummary();
                    }
                });
            },

            setGiftcardDiscount: function (totalDiscount) {
                this.giftcardDiscount(totalDiscount);
            },

            setRemainingAmount: function (amount) {
                this.remainingAmount(amount);
            },

            setShowgiftcardSummary: function (isRedeemed) {
                this.showGiftcardSummary(isRedeemed);
            },

            resetGiftcardSummary: function () {
                this.setGiftcardDiscount(0);
                this.setRemainingAmount(0);
                this.setShowgiftcardSummary(false);
            },

            setGiftcardSummaryBlock: function (totalDiscount, remainingAmount, showBlock) {
                this.setGiftcardDiscount(totalDiscount);
                this.setRemainingAmount(remainingAmount);
                this.setShowgiftcardSummary(showBlock);
            }
        });
    }
);
