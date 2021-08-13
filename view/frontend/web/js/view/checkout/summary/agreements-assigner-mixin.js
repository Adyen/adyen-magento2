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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

define([
    'mage/utils/wrapper',
    'jquery'

], function (wrapper, $) {
    'use strict';

    return function (agreementsAssignerAction) {
        return wrapper.wrap(agreementsAssignerAction, function (originalAction, paymentData) {
            originalAction(paymentData);
            var agreementIds = paymentData['extension_attributes']['agreement_ids'];
            if (paymentData.additional_data && paymentData.additional_data.stateData) {
                let data = paymentData['additional_data']['stateData'];
                let stateData = JSON.parse(data);
                var checkoutConfig = window.checkoutConfig;
                if (checkoutConfig.checkoutAgreements.isEnabled
                    && stateData.paymentMethod.type == 'amazonpay'
                    && !agreementIds.length) {
                    var agreementsConfig = checkoutConfig.checkoutAgreements?.agreements ?
                        checkoutConfig.checkoutAgreements.agreements : [];
                    for (let i = 1; i <= agreementsConfig.length; i++) {
                        agreementIds[i] = i;
                    }
                }
            }
            paymentData['extension_attributes']['agreement_ids'] = agreementIds;

            return paymentData;
        });
    };
});




