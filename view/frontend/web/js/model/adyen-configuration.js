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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [],
    function() {
        'use strict';
        return {
            getClientKey: function() {
                return window.checkoutConfig.payment.adyen.clientKey;
            },
            showLogo: function() {
                return window.checkoutConfig.payment.adyen.showLogo;
            },
            getLocale: function() {
                return window.checkoutConfig.payment.adyen.locale;
            },
            getCheckoutEnvironment: function() {
                return window.checkoutConfig.payment.adyen.checkoutEnvironment;
            },
            getChargedCurrency: function() {
                return window.checkoutConfig.payment.adyen.chargedCurrency;
            },
            getHasHolderName: function() {
                return window.checkoutConfig.payment.adyen.hasHolderName;
            },
            getHolderNameRequired: function() {
                return window.checkoutConfig.payment.adyen.holderNameRequired;
            },
            getHouseNumberStreetLine: function() {
                return window.checkoutConfig.payment.adyen.houseNumberStreetLine;
            },
            getCustomerStreetLinesEnabled: function () {
                return window.checkoutConfig.payment.customerStreetLinesEnabled;
            },
        };
    },
);
