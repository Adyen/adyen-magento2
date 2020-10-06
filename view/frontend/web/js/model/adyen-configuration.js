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
    [
    ],
    function () {
      'use strict';
      return {
        getOriginKey: function () {
          return window.checkoutConfig.payment.adyen.originKey;
        },
        showLogo: function () {
          return window.checkoutConfig.payment.adyen.showLogo;
        },
        getLocale: function () {
          return window.checkoutConfig.payment.adyen.locale;
        },
        getCheckoutEnvironment: function () {
          return window.checkoutConfig.payment.adyen.checkoutEnvironment;
        },
      };
    }
);