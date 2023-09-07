/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*global define*/
define([
    'Adyen_Payment/js/view/payment/method-renderer/multishipping/adyen-pm-method',
    'Adyen_Payment/js/helper/configHelper'
], function (
    Component,
    configHelper
) {
    'use strict';

    return Component.extend({
        buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo) {
            let configuration = configHelper.buildComponentConfiguration(paymentMethod, paymentMethodsExtraInfo, this);
            configuration.showPayButton = true;
            return configuration;
        }
    });
});
