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
    'Adyen_Payment/js/view/payment/method-renderer/adyen-cc-method',
    'Adyen_Payment/js/model/adyen-configuration',
], function (
    $, Component, adyenConfiguration
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Adyen_Payment/payment/multishipping/cc-form'
        },
        selectPaymentMethod: function () {
            this.renderSecureFields();
            return this._super();
        },
        renderSecureFields: function () {
            var self = this;

            if (!self.getClientKey) {
                return;
            }
            self.cardComponent = self.checkoutComponent.create('card', {
                enableStoreDetails: self.getEnableStoreDetails(),
                brands: self.getAvailableCardTypeAltCodes(),
                hasHolderName: adyenConfiguration.getHasHolderName(),
                holderNameRequired: adyenConfiguration.getHasHolderName() &&
                    adyenConfiguration.getHolderNameRequired(),
                onChange: function (state) {
                    $('#stateData').val(state.isValid ? JSON.stringify(state.data) : '');
                    self.placeOrderAllowed(!!state.isValid);
                    self.storeCc = !!state.data.storePaymentMethod;
                }
            }).mount('#cardContainer');
        }
    });
});
