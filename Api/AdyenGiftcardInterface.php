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

namespace Adyen\Payment\Api;

/**
 * Interface for managing redeemed Adyen giftcards
 */
interface AdyenGiftcardInterface
{
    /**
     * Fetches all the adyen_state_data entities and returns giftcard related objects.
     *
     * @param int $cartId
     * @return string
     */
    public function getRedeemedGiftcards(int $cartId): string;
}
