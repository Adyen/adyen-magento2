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
define(
    [
        'Adyen_Payment/js/model/adyen-configuration',
    ], function (adyenConfiguration) {
        "use strict";
        return function (target) {
            return target.extend({
                    /**
                     * @return {*}
                     */
                    isBaseGrandTotalDisplayNeeded: function () {
                        let total = this.totals();
                        if (!total) {
                            return false;
                        }
                        let chargedCurrency = adyenConfiguration.getChargedCurrency();
                        return chargedCurrency === 'base' &&
                            (total['base_currency_code'] != total['quote_currency_code']); //eslint-disable-line eqeqeq
                    }
                }
            );
        }
    })
;