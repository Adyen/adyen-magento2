/**
 * @api
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer'
    ],
    function ($,
              quote,
              urlBuilder,
              storage,
              errorProcessor,
              customer
    ) {
        'use strict';

        return function (messageContainer) {
            return new Promise(function(resolve, reject) {
                let serviceUrl,
                    payload;

                /**
                 * Checkout for guest and registered customer.
                 */
                if (!customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/billing-address', {
                        cartId: quote.getQuoteId()
                    });
                    payload = {
                        cartId: quote.getQuoteId(),
                        address: quote.billingAddress()
                    };
                } else {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/billing-address', {});
                    payload = {
                        cartId: quote.getQuoteId(),
                        address: quote.billingAddress()
                    };
                }

                storage.post(
                    serviceUrl, JSON.stringify(payload)
                ).success(
                    resolve()
                ).fail(
                    function (response) {
                        errorProcessor.process(response, messageContainer);
                        reject();
                    }
                );
            });
        };
    }
);
