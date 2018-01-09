/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/quote'
    ],
    function ($, Component, additionalValidators, fullScreenLoader, placeOrderAction, quote) {
        'use strict';
        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/pos-form'
            },
            initObservable: function () {
                this._super()
                    .observe([]);
                return this;
            },
            /** Redirect to adyen */
            continueToAdyen: function () {
                var self = this;

                if (this.validate() && additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.isPlaceOrderActionAllowed(false);
                    fullScreenLoader.startLoader();

                    $.when(
                        placeOrderAction(this.getData(), this.messageContainer)
                    ).fail(
                        function () {
                            self.isPlaceOrderActionAllowed(true);
                        }
                    ).done(
                        function () {
                            self.afterPlaceOrder();
                            $.mage.redirect(
                                window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl
                            );
                        }
                    );
                }
                return false;
            },
            showLogo: function () {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            validate: function () {
                return true;
            }
        });
    }
);
