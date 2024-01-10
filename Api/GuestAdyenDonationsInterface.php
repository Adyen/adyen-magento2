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

interface GuestAdyenDonationsInterface
{
    /**
     * Build and send donation payment request for guest shoppers
     *
     * @param string $cartId
     * @param string $payload
     * @return void
     */
    public function donate(string $cartId, string $payload): void;
}
