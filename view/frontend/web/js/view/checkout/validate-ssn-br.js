define([
    'jquery',
    'jquery/ui',
    'jquery/validate',
    'mage/translate'
], function($){
    'use strict';
    return function() {
        $.validator.addMethod(
            "validate-ssn-br",
            function(value, element) {
                // The format is 000.000.000-00
                return utils.isEmptyNoTrim(value) || /^\d{3}.?\d{3}.?\d{3}-?\d{2}$/.test(value);
            },
            $.mage.__('Please enter a valid brazilian social security number (Ex: 123.456.789-10).')
        );
    }
});
