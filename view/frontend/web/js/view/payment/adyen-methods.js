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
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
        'Adyen_Payment/js/model/adyen-payment-service',
        'Adyen_Payment/js/model/adyen-configuration',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_SalesRule/js/action/set-coupon-code',
        'Magento_SalesRule/js/action/cancel-coupon'
    ],
    function (
        Component,
        rendererList,
        adyenPaymentService,
        adyenConfiguration,
        quote,
        fullScreenLoader,
        setCouponCodeAction,
        cancelCouponAction
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'adyen_oneclick',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-oneclick-method'
            },
            {
                type: 'adyen_cc',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-cc-method'
            },
            {
                type: 'adyen_hpp',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-hpp-method'
            },
            {
                type: 'adyen_boleto',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-boleto-method'
            },
            {
                type: 'adyen_pos_cloud',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-pos-cloud-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({
            initialize: function () {
                this._super();

                var shippingAddressCountry = "";
                var retrievePaymentMethods = function (){
                    fullScreenLoader.startLoader();
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
                                                       };
                quote.shippingAddress.subscribe(function(address) {
                    // In case the country hasn't changed don't retrieve new payment methods
                    if (shippingAddressCountry === quote.shippingAddress().countryId) {
                        return;
                    }
                    shippingAddressCountry = quote.shippingAddress().countryId;
                    retrievePaymentMethods();
                });
                //Retrieve payment methods to ensure the amount is updated, when applying the discount code
                setCouponCodeAction.registerSuccessCallback(function () {
                    retrievePaymentMethods();
                });
                //Retrieve payment methods to ensure the amount is updated, when canceling the discount code
                cancelCouponAction.registerSuccessCallback(function () {
                    retrievePaymentMethods();
                });
            }
        });
    }
);
