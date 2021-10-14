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

    // Since Amazon Pay requires the checkout page to be loaded twice 
    // the agreements are confirmed automatically on the second redirect
    return function (agreementsAssignerAction) {
        return wrapper.wrap(agreementsAssignerAction, function (originalAction, paymentData) {
            originalAction(paymentData);
            var checkoutConfig = window.checkoutConfig;
            if (!checkoutConfig.checkoutAgreements.isEnabled) {
                return;
            }
            var agreementIds = paymentData['extension_attributes']['agreement_ids'];
            if (paymentData.additional_data && paymentData.additional_data.stateData) {
                let data = paymentData['additional_data']['stateData'];
                let stateData = JSON.parse(data);
                if (checkoutConfig.checkoutAgreements.isEnabled
                    && stateData.paymentMethod.type == 'amazonpay'
                    && !agreementIds.length) {
                    var agreementsConfig =
                        (checkoutConfig.checkoutAgreements && checkoutConfig.checkoutAgreements.agreements) ?
                        checkoutConfig.checkoutAgreements.agreements : [];
                    for (let i = 0; i < agreementsConfig.length; i++) {
                        agreementIds[i] = agreementsConfig[i].agreementId;
                    }
                }
            }
            paymentData['extension_attributes']['agreement_ids'] = agreementIds;

            return paymentData;
        });
    };
});




