/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'ko',
    ],
    function (ko) {
        'use strict';
        var installments = ko.observableArray([]);
        return {
            /**
             * Populate the list of installments
             * @param {Array} methods
             */
            setInstallments: function (installmentData) {
                // remove everything from the current list
                installments.removeAll();

                // populate installments
                var i;
                for (i = 1; i <= installmentData; i++) {
                    installments.push({
                        key: i,
                        value: i
                    });
                }
            },
            /**
             * Get the list of available installments.
             * @returns {Array}
             */
            getInstallments: function () {
                return installments;
            }
        };
    }
);
