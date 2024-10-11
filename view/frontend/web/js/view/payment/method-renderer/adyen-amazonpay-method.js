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
        'Adyen_Payment/js/model/adyen-checkout',
        'mage/url'
    ],
    function(
        quote,
        adyenPaymentMethod,
        adyenCheckout,
        urlBuilder
    ) {
        const amazonSessionKey = 'amazonCheckoutSessionId';
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let self = this;
                let formattedShippingAddress = {};
                let formattedBillingAddress = {};
                let baseComponentConfiguration = this._super();

                baseComponentConfiguration = Object.assign(
                    baseComponentConfiguration,
                    paymentMethodsExtraInfo[paymentMethod.type].configuration
                );

                if (!quote.isVirtual() && !!quote.shippingAddress()) {
                    formattedShippingAddress = self.getFormattedAddress(quote.shippingAddress());
                }

                if (!!quote.billingAddress()) {
                    formattedBillingAddress = self.getFormattedAddress(quote.billingAddress());
                }

                baseComponentConfiguration.onClick = function(resolve,reject) {
                    if (self.validate()) {
                        resolve();
                    } else {
                        reject();
                    }
                }
                baseComponentConfiguration.onSubmit = async (state, amazonPayComponent) => {
                    try {
                        await self.handleOnSubmit(state.data, amazonPayComponent);
                    } catch (error) {
                        amazonPayComponent.handleDeclineFlow();
                    }
                };

                baseComponentConfiguration.productType = 'PayAndShip';
                baseComponentConfiguration.checkoutMode = 'ProcessOrder';
                baseComponentConfiguration.showPayButton = true;

                // Redirect shoppers to the cart page if they cancel the payment on Amazon Pay hosted page.
                baseComponentConfiguration.cancelUrl = urlBuilder.build('checkout/cart');
                // Redirect shoppers to the checkout if they complete the payment on Amazon Pay hosted page.
                baseComponentConfiguration.returnUrl = urlBuilder.build('checkout/#payment');

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
            },

            mountPaymentMethodComponent: function (paymentMethod, configuration) {
                let self = this;
                const containerId = '#' + paymentMethod.type + 'Container';
                let url = new URL(location.href);
                //Handles the redirect back to checkout page with amazonSessionKey in url
                if (url.searchParams.has(amazonSessionKey)) {
                    let componentConfig = {
                        amazonCheckoutSessionId: url.searchParams.get(amazonSessionKey),
                        showOrderButton: false,
                        amount: {
                            currency: configuration.amount.currency,
                            value: configuration.amount.value
                        },
                        showChangePaymentDetailsButton: false
                    }
                    try {
                        const amazonPayComponent = adyenCheckout.mountPaymentMethodComponent( // This mountPaymentMethodCOmponent isn't up to date.
                            self.checkoutComponent,
                            'amazonpay',
                            componentConfig,
                            containerId
                        );
                        amazonPayComponent.submit();
                    } catch (err) {
                        // The component does not exist yet
                        if ('test' === adyenConfiguration.getCheckoutEnvironment()) {
                            console.log(err);
                        }
                    }
                } else{
                    this._super();
                }
            },

            handleOnFailure: function (response, component) {
                debugger;
            }
        })
    }
);
