/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
/*global define*/
define([
    'jquery',
    'Adyen_Payment/js/view/payment/method-renderer/adyen-klarna-method',
    'Adyen_Payment/js/helper/configHelper'
], function (
    $,
    Component,
    configHelper
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/abstract-form'
        },

        selectPaymentMethod: function () {
            $('#stateData').val(JSON.stringify(this.paymentComponent.data));
            return this._super();
        },

        buildComponentConfiguration: function(paymentMethod, paymentMethodsExtraInfo) {
            return configHelper.buildComponentConfiguration(paymentMethod, paymentMethodsExtraInfo);
        }
    });
});
