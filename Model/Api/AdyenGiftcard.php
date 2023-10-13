<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenGiftcardInterface;
use Adyen\Payment\Helper\GiftcardPayment;

class AdyenGiftcard implements AdyenGiftcardInterface
{
    private GiftcardPayment $giftcardPaymentHelper;

    public function __construct(
        GiftcardPayment $giftcardPaymentHelper
    ) {
        $this->giftcardPaymentHelper = $giftcardPaymentHelper;
    }

    public function getRedeemedGiftcards(int $cartId): string
    {
        return $this->giftcardPaymentHelper->fetchRedeemedGiftcards($cartId);
    }
}
