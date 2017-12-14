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
                console.log(installmentData);
                installments.push({
                    key: "Do not use installments",
                    value: 1
                } );
                // populate installments
                var i;
                for (i = 0; i < installmentData.length; i++) {
                    installments.push({
                        key: installmentData[i],
                        value: installmentData[i]
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
