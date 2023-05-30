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
    const VALID_GIFTCARD_REQUEST_FIELDS = [
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

    /**
     * @param array $request
     * @param array $orderData
     * @param array $stateData
     * @param int $amount
     * @return array
     */
    public function buildGiftcardPaymentRequest(
        array $request,
        array $orderData,
        array $stateData,
        int $amount
    ): array {
        $giftcardPaymentRequest = [];

        foreach (self::VALID_GIFTCARD_REQUEST_FIELDS as $key) {
            if (isset($request[$key])) {
                $giftcardPaymentRequest[$key] = $request[$key];
            }
        }

        $giftcardPaymentRequest['paymentMethod'] = $stateData['paymentMethod'];
        $giftcardPaymentRequest['amount']['value'] = $amount;

        $giftcardPaymentRequest['order']['pspReference'] = $orderData['pspReference'];
        $giftcardPaymentRequest['order']['orderData'] = $orderData['orderData'];

        return $giftcardPaymentRequest;
    }
}
