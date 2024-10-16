/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

define([
    'uiComponent',
    'ko',
], function (
    Component,
    ko
) {
    'use strict';
    return Component.extend({
        states: [],

        initializeState: function (
            methodCode,
            isPlaceOrderAllowed = false
        ) {
            this.states[methodCode] = {
                isPlaceOrderAllowed: ko.observable(isPlaceOrderAllowed)
            };
        },

        getState: function (methodCode) {
            if (!!this.states[methodCode]) {
                return this.states[methodCode];
            } else {
                throw "Payment component state does not exist!";
            }
        },

        setIsPlaceOrderAllowed: function (methodCode, isPlaceOrderAllowed) {
            let state = this.getState(methodCode);
            this.states[methodCode].isPlaceOrderAllowed(isPlaceOrderAllowed);
        },

        getIsPlaceOrderAllowed: function (methodCode) {
            let state = this.getState(methodCode);
            return state.isPlaceOrderAllowed();
        }
    });
});