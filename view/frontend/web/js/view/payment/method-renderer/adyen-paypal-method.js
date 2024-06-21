/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
        'Magento_Checkout/js/model/full-screen-loader',
    ],
    function(
        quote,
        adyenPaymentMethod,
        fullScreenLoader
    ) {
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();
                let paypalConfiguration = Object.assign(baseComponentConfiguration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                paypalConfiguration.showPayButton = true;

                /*
                 * Add onInit and onClick functions to the configuration object in order to maange the PayPal Buttons
                 * state (enabled / disabled) depending on the checkout agreements checkbox state.
                 *
                 * Without these functions, if the form is not valid (checkbox unchecked) and the user clicks on the
                 * PayPal button, the popup will be opened with an infinite loading spinner, and even if the customer
                 * closes the popup, checks the checkbox and clicks again on the PayPal button, the popup will not work
                 * anymore, until the page is reloaded.
                 */
                let checkoutAgreementsInput = document.querySelector('.checkout-agreement input');

                if (null !== checkoutAgreementsInput) {
                    let self = this;

                    paypalConfiguration.onInit = function (data, actions) {
                        actions.disable();

                        document.querySelector('.form-checkout-agreements input')
                            .addEventListener('change', function (event) {
                                // Enable or disable the button when it is checked or unchecked
                                if (event.target.checked) {
                                    actions.enable();
                                } else {
                                    actions.disable();
                                }
                            });
                    };

                    paypalConfiguration.onClick = function (data, actions) {
                        // Trigger the form validation in order to display potential error messages if the buttons 
                        // have been disabled.
                        self.validate();
                    };
                }

                return paypalConfiguration
            },
            renderActionComponent: function(resultCode, action, component) {
                fullScreenLoader.stopLoader();

                this.actionComponent = component.handleAction(action);
            },
        })
    }
);
