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
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'Adyen_Payment/js/action/set-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators'
    ],
    function (ko, Component, setPaymentMethodAction, additionalValidators) {
        'use strict';
        var brandCode = ko.observable(null);
        var paymentMethod = ko.observable(null);

        return Component.extend({
            self: this,
            defaults: {
                template: 'Adyen_Payment/payment/pos-form',
                brandCode: ''
            },
            initObservable: function () {
                this._super()
                    .observe([
                    ]);
                return this;
            },
            /** Redirect to adyen */
            continueToAdyen: function () {
                if (this.validate() && additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    setPaymentMethodAction();
                    return false;
                }
            },
            showLogo: function() {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            validate: function () {
                return true;
            }
        });
    }
);
