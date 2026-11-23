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
        AdyenWeb
    ) {
        'use strict';

        const disposedComponents = new WeakSet();

        return {
            buildCheckoutComponent: function (
                paymentMethodsResponse,
                countryCode,
                handleOnAdditionalDetails,
                handleOnSubmit = undefined,
                handleOnError = undefined
            ) {
                if (!!paymentMethodsResponse.paymentMethodsResponse) {
                    return window.AdyenWeb.AdyenCheckout({
                            locale: adyenConfiguration.getLocale(),
                            countryCode: countryCode,
                            clientKey: adyenConfiguration.getClientKey(),
                            environment: adyenConfiguration.getCheckoutEnvironment(),
                            paymentMethodsResponse: paymentMethodsResponse.paymentMethodsResponse,
                            onAdditionalDetails: handleOnAdditionalDetails,
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

                        const paymentMethodComponent = window.AdyenWeb.createComponent(
                            paymentMethodType,
                            checkoutComponent,
                            configuration
                        );

                        if ('isAvailable' in paymentMethodComponent) {
                            paymentMethodComponent.isAvailable().then(() => {
                                // The component may have been disposed while its availability was checked.
                                if (!disposedComponents.has(paymentMethodComponent)) {
                                    paymentMethodComponent.mount(elementLabel);
                                }
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
            },

            // Unmounts the component and stops a pending mountPaymentMethodComponent() from mounting it later.
            disposePaymentMethodComponent(paymentMethodComponent) {
                if (!paymentMethodComponent) {
                    return;
                }

                disposedComponents.add(paymentMethodComponent);

                if (typeof paymentMethodComponent.unmount === 'function') {
                    try {
                        paymentMethodComponent.unmount();
                    } catch (e) {
                        console.warn('Failed to unmount Adyen component:', e);
                    }
                }
            }
        };
    }
);
