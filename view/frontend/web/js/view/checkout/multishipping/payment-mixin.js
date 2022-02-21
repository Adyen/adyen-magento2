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
    'jquery'
], function ($) {
    'use strict';
    return function (originalWidget) {
        const widgetFullName = originalWidget.prototype.namespace + '.' + originalWidget.prototype.widgetName;
        jQuery.widget(
            widgetFullName,
            jQuery[originalWidget.prototype.namespace][originalWidget.prototype.widgetName], {
                _validatePaymentMethod: function () {
                    let isValid;
                    let selectedPaymentMethod = $('#payment-methods input[name="payment[method]"]:checked').val();
                    if (!!selectedPaymentMethod && selectedPaymentMethod.startsWith('adyen')) {
                        isValid = !!$('#stateData').val();
                    } else {
                        isValid = true;
                    }
                    return isValid && this._super();
                }
            });
    }
});
