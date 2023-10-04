/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'Adyen_Payment/js/model/adyen-payment-service'
], function ($,
    wrapper,
    adyenPaymentService
) {
    'use strict';

    return function (shippingInformationAction) {
        return wrapper.wrap(shippingInformationAction, function (originalAction) {
            return originalAction().then(function (result) {
                if (!!result.extension_attributes) {
                    let adyenPaymentMethodsResponse = result.extension_attributes.adyen_payment_methods_response;
                    let adyenConnectedTerminals = result.extension_attributes.adyen_connected_terminals;

                    if (adyenPaymentMethodsResponse) {
                        adyenPaymentService.setPaymentMethods(JSON.parse(adyenPaymentMethodsResponse));
                    }

                    if (adyenConnectedTerminals) {
                        adyenPaymentService.setConnectedTerminals(adyenConnectedTerminals);
                    }
                }

                return result;
            });
        });
    };
});
