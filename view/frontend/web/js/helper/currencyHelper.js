/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define([], function () {
    'use strict';

    return {
        decimalNumbers: function (currency) {
            let format;
            switch (currency) {
                case "CVE":
                case "DJF":
                case "GNF":
                case "IDR":
                case "JPY":
                case "KMF":
                case "KRW":
                case "PYG":
                case "RWF":
                case "UGX":
                case "VND":
                case "VUV":
                case "XAF":
                case "XOF":
                case "XPF":
                    format = 0;
                    break;
                case "BHD":
                case "IQD":
                case "JOD":
                case "KWD":
                case "LYD":
                case "OMR":
                case "TND":
                    format = 3;
                    break;
                default:
                    format = 2;
            }
            return format;
        },
        formatAmount: function (amount, currency) {
            let decimals = this.decimalNumbers(currency);
            let numericAmount = Number(amount);
            return parseInt(numericAmount.toFixed(decimals).replace('.', ''));
        }
    };
});
