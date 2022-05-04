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
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'ko',
        'jquery',
        'underscore',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/adyen'
    ],
    function(
        ko,
        $,
        _,
        adyenConfiguration,
        AdyenCheckout,
    ) {
        'use strict';
        return {
            buildCheckoutComponent: async function(paymentMethodsResponse, handleOnAdditionalDetails, handleOnCancel = undefined, handleOnSubmit = undefined) {
                if (!!paymentMethodsResponse.paymentMethodsResponse) {
                    return await AdyenCheckout({
                            locale: adyenConfiguration.getLocale(),
                            clientKey: adyenConfiguration.getClientKey(),
                            environment: adyenConfiguration.getCheckoutEnvironment(),
                            paymentMethodsResponse: paymentMethodsResponse.paymentMethodsResponse,
                            onAdditionalDetails: handleOnAdditionalDetails,
                            onCancel: handleOnCancel,
                            onSubmit: handleOnSubmit
                        }
                    );
                } else {
                    return false
                }
            }
        };
    }
);
