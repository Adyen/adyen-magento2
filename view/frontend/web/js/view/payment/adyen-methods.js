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
        'Magento_Customer/js/model/customer'
    ],
    function (
        Component,
        rendererList,
        adyenPaymentService,
        adyenConfiguration,
        quote,
        customer
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
                type: 'adyen_apple_pay',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-apple-pay-method'
            },
            {
                type: 'adyen_pos_cloud',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-pos-cloud-method'
            },
            {
                type: 'adyen_google_pay',
                component: 'Adyen_Payment/js/view/payment/method-renderer/adyen-google-pay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({
            defaults: {
                countryCode: ""
            },
            initialize: function () {
                var self = this;

                this._super();

                if (this.isGooglePayEnabled()) {
                    var googlepayscript = document.createElement('script');
                    googlepayscript.src = "https://pay.google.com/gp/p/js/pay.js";
                    googlepayscript.type = "text/javascript";
                    document.head.appendChild(googlepayscript);
                }

                if (customer.isLoggedIn()) {
                    self.setAdyenPaymentMethods();
                }

                quote.shippingAddress.subscribe(function() {
                    if (!!quote.shippingAddress().countryId && self.countryCode !== quote.shippingAddress().countryId) {
                        self.countryCode = quote.shippingAddress().countryId;
                        self.setAdyenPaymentMethods();
                    }
                })
            },
            setAdyenPaymentMethods: function() {
                adyenPaymentService.retrieveAvailablePaymentMethods().done(function (response) {
                    var responseJson = JSON.parse(response);
                    var paymentMethodsResponse = responseJson.paymentMethodsResponse;

                    // TODO check if this is still required or if can be outsourced for the generic component, or checkout can create a ratepay component
                    /*if (!!window.checkoutConfig.payment.adyenHpp) {
                        if (JSON.stringify(paymentMethods).indexOf("ratepay") > -1) {

                          var ratePayId = window.checkoutConfig.payment.adyenHpp.ratePayId;
                           var dfValueRatePay = self.getRatePayDeviceIdentToken();

                           window.di = {
                               t: dfValueRatePay.replace(':', ''),
                               v: ratePayId,
                               l: 'Checkout'
                           };

                           // Load Ratepay script
                           var ratepayScriptTag = document.createElement('script');
                           ratepayScriptTag.src = "//d.ratepay.com/" + ratePayId + "/di.js";
                           ratepayScriptTag.type = "text/javascript";
                           document.body.appendChild(ratepayScriptTag);
                        }
                    }*/

                    // Initialises adyen checkout main component with default configuration
                    adyenPaymentService.initCheckoutComponent(
                        paymentMethodsResponse,
                        adyenConfiguration.getOriginKey(),
                        adyenConfiguration.getLocale(),
                        adyenConfiguration.getCheckoutEnvironment()
                    );
                })
            },
            isGooglePayEnabled: function() {
                return window.checkoutConfig.payment.adyenGooglePay.active;
            },
            getRatePayDeviceIdentToken: function () {
                return window.checkoutConfig.payment.adyenHpp.deviceIdentToken;
            }
        });
    }
);
