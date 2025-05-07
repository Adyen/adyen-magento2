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
        'Adyen_Payment/js/model/adyen-configuration',
        'mage/url'
    ],
    function(
        quote,
        adyenPaymentMethod,
        adyenCheckout,
        adyenConfiguration,
        urlBuilder
    ) {
        const amazonSessionKey = 'amazonCheckoutSessionId';
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            amazonPayComponent: null,

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

                baseComponentConfiguration.productType = 'PayAndShip';
                baseComponentConfiguration.checkoutMode = 'ProcessOrder';
                baseComponentConfiguration.showPayButton = true;
                baseComponentConfiguration.locale = adyenConfiguration.getLocale();

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
                const containerId = '#' + paymentMethod.type + 'Container';
                const currentUrl = new URL(location.href);

                /*
                 * If the first redirect is successful and URL contains `amazonCheckoutSessionId` parameter,
                 * don't mount the default component but mount the second component to submit the `/payments` request.
                 */
                if (currentUrl.searchParams.has(amazonSessionKey)) {
                    let componentConfig = {
                        amazonCheckoutSessionId: currentUrl.searchParams.get(amazonSessionKey),
                        showOrderButton: false,
                        amount: {
                            currency: configuration.amount.currency,
                            value: configuration.amount.value
                        },
                        showChangePaymentDetailsButton: false
                    }

                    this.amazonPayComponent = adyenCheckout.mountPaymentMethodComponent(
                        this.checkoutComponent,
                        'amazonpay',
                        componentConfig,
                        containerId
                    );

                    // Triggers `onSubmit` event and `handleOnSubmit()` callback in adyen-pm-method.js handles it
                    this.amazonPayComponent.submit();
                } else {
                    this._super();
                }
            },

            /*
             * Try to handle decline flow if Amazon Pay session allows in case of `/payments` call fails.
             * If decline flow is available, shopper will be redirected to Amazon Pay hosted page again.
             * If handle decline flow is not present, the component will throw `onError` event.
             */
            handleOnFailure: function (response, component) {
                this.amazonPayComponent.handleDeclineFlow();
            },

            /*
            * If `handleDeclineFlow()` can not be handled for any reason, `onError` will be thrown.
            * In this case, remove `amazonCheckoutSessionId` from the URL and remount the payment component.
            */
            handleOnError: function (error, component) {
                this.remountAmazonPayComponent();
            },

            /*
             * Remove `amazonCheckoutSessionId` from the URL and remount the component.
             */
            remountAmazonPayComponent: function () {
                const checkoutPaymentUrl = "checkout/#payment";
                window.history.pushState({}, document.title, "/" + checkoutPaymentUrl);

                this.createCheckoutComponent(true);
            }
        })
    }
);
