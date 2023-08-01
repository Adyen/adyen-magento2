/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
    ],
    function(
        quote,
        adyenPaymentMethod,
    ) {
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            txVariant: 'amazonpay',
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let self = this;
                let formattedShippingAddress = {};
                let formattedBillingAddress = {};
                let baseComponentConfiguration = this._super();
                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (!!quote.billingAddress()) {
                    formattedBillingAddress = self.getFormattedAddress(quote.billingAddress());
                }
                baseComponentConfiguration.showPayButton = true;
                baseComponentConfiguration.onClick = function(resolve,reject) {
                    if (self.validate()) {
                        resolve();
                    } else {
                        reject();
                    }
                }
                baseComponentConfiguration = Object.assign(baseComponentConfiguration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                baseComponentConfiguration.productType = 'PayAndShip';
                baseComponentConfiguration.checkoutMode = 'ProcessOrder';
                let url = new URL(location.href);
                url.searchParams.delete('amazonCheckoutSessionId');
                baseComponentConfiguration.returnUrl = url.href;
                baseComponentConfiguration.onSubmit = async (state, amazonPayComponent) => {
                    try {
                        await self.handleOnSubmit(state.data, amazonPayComponent);
                    } catch (error) {
                        amazonPayComponent.handleDeclineFlow();
                    }
                };

                if (formattedShippingAddress &&
                    formattedShippingAddress.telephone) {
                    baseComponentConfiguration.addressDetails = {
                        name: formattedShippingAddress.firstName +
                            ' ' +
                            formattedShippingAddress.lastName,
                        addressLine1: formattedShippingAddress.street,
                        addressLine2: formattedShippingAddress.houseNumber,
                        city: formattedShippingAddress.city,
                        postalCode: formattedShippingAddress.postalCode,
                        countryCode: formattedShippingAddress.country,
                        phoneNumber: formattedShippingAddress.telephone
                    };
                    if (baseComponentConfiguration.addressDetails.countryCode === 'US') {
                        baseComponentConfiguration.addressDetails.stateOrRegion = quote.shippingAddress().regionCode
                    }
                } else if (formattedBillingAddress &&
                    formattedBillingAddress.telephone) {
                    baseComponentConfiguration.addressDetails = {
                        name: formattedBillingAddress.firstName +
                            ' ' +
                            formattedBillingAddress.lastName,
                        addressLine1: formattedBillingAddress.street,
                        addressLine2: formattedBillingAddress.houseNumber,

                        city: formattedBillingAddress.city,
                        postalCode: formattedBillingAddress.postalCode,
                        countryCode: formattedBillingAddress.country,
                        phoneNumber: formattedBillingAddress.telephone
                    };
                    if (baseComponentConfiguration.addressDetails.countryCode === 'US') {
                        baseComponentConfiguration.addressDetails.stateOrRegion = quote.billingAddress().regionCode
                    }
                }
                return baseComponentConfiguration;
            }
        })
    }
);
