/**
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
                // The CPF format is: 000.000.000-00
                const cpf = /^\d{3}\.\d{3}\.\d{3}\-\d{2}$/.test(value);
                // The CNPJ format is: 00.000.000/0000-00
                const cnpj = /^\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}$/.test(value);
                return cpf || cnpj;
            },
            $.mage.__('Please enter a valid brazilian social security number (Ex: 123.456.789-10 or 12.345.678/1234-56).')
        )

        return target;
    }
});
