<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

class GiftcardPayment
{
    const validGiftcardPaymentRequestFields = [
        'merchantAccount',
        'shopperReference',
        'shopperEmail',
        'telephoneNumber',
        'shopperName',
        'countryCode',
        'shopperLocale',
        'shopperIP',
        'billingAddress',
        'deliveryAddress',
        'amount',
        'reference',
        'additionalData',
        'fraudOffset',
        'browserInfo',
        'shopperInteraction',
        'returnUrl',
        'channel',
        'origin'
    ];
}
