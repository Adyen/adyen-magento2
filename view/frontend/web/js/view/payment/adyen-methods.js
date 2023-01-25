/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
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
        const paymentMethodComponent = 'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method';
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
                type: 'adyen_boleto',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-boleto-method'
            },
            {
                type: 'adyen_pos_cloud',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-pos-cloud-method'
            },
            {
                type: 'adyen_ideal',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-ideal-method'
            },
            {
                type: 'adyen_klarna',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-klarna-method'
            },
            {
                type: 'adyen_paypal',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-paypal-method'
            },
            {
                type: 'adyen_dotpay',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-dotpay-method'
            },
            {
                type: 'adyen_bcmc_mobile',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-bcmc-method'
            },
            {
                type: 'adyen_googlepay',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-googlepay-method'
            },
        );

        /** Add view logic here if needed */
        return Component.extend({
            initialize: function () {
                this._super();

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
                retrievePaymentMethods();
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
