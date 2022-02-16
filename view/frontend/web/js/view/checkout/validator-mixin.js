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
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

define(['jquery'], function($) {
    'use strict';

    return function(target) {
        $.validator.addMethod(
            "validate-ssn-br",
            function(value, element) {
                // The format is 000.000.000-00
                return /^([-\.\s]?(\d{3})){3}[-\.\s]?(\d{2})$/.test(value);
            },
            $.mage.__('Please enter a valid brazilian social security number (Ex: 123.456.789-10).')
        )

        return target;
    }
});
