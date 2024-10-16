/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'jquery',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/adyen'
    ],
    function (
        $,
        adyenConfiguration,
        AdyenCheckout,
    ) {
        'use strict';
        return {
            buildCheckoutComponent: function (paymentMethodsResponse, handleOnAdditionalDetails, handleOnCancel = undefined, handleOnSubmit = undefined, handleOnError = undefined) {
                if (!!paymentMethodsResponse.paymentMethodsResponse) {
                    return AdyenCheckout({
                            locale: adyenConfiguration.getLocale(),
                            clientKey: adyenConfiguration.getClientKey(),
                            environment: adyenConfiguration.getCheckoutEnvironment(),
                            paymentMethodsResponse: paymentMethodsResponse.paymentMethodsResponse,
                            onAdditionalDetails: handleOnAdditionalDetails,
                            onCancel: handleOnCancel,
                            onSubmit: handleOnSubmit,
                            onError: handleOnError
                        }
                    );
                } else {
                    return false
                }
            },
            mountPaymentMethodComponent(checkoutComponent, paymentMethodType, configuration, elementLabel, result = undefined) {
                if ($(elementLabel).length) {
                    try {
                        // Gift cards do not have a Web Component (they fallback to HPP)
                        if ('giftcard' === configuration.type) {
                            return false;
                        }

                        const paymentMethodComponent = checkoutComponent.create(
                            paymentMethodType,
                            configuration
                        )

                        if ('isAvailable' in paymentMethodComponent) {
                            paymentMethodComponent.isAvailable().then(() => {
                                paymentMethodComponent.mount(elementLabel);
                            }).catch(e => {
                                if (!!result) {
                                    result.isAvailable(false); // Set observable to false, to match component availability
                                }
                            });
                        } else {
                            paymentMethodComponent.mount(elementLabel);
                        }

                        return paymentMethodComponent;
                    } catch (err) {
                        if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                            console.error(err);
                        }
                    }
                }

                return false;
            }
        };
    }
);
