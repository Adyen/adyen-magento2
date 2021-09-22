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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*global define*/
define([
    'Adyen_Payment/js/view/payment/method-renderer/adyen-hpp-method',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Magento_Checkout/js/model/full-screen-loader'
], function (
    Component,
    adyenPaymentService,
    fullScreenLoader
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/hpp-form'
        },
        initialize: function () {
            // Retrieve adyen payment methods
            adyenPaymentService.retrievePaymentMethods().done(function(paymentMethods) {
                try {
                    paymentMethods = JSON.parse(paymentMethods);
                } catch(error) {
                    console.log(error);
                    paymentMethods = null;
                }
                adyenPaymentService.setPaymentMethods(paymentMethods);
                fullScreenLoader.stopLoader();
            }).fail(function() {
                console.log('Fetching the payment methods failed!');
            });
            this._super();
        }
    });
});