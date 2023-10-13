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
 * Interface for querying the Adyen order payment status for guest shoppers
 */
interface GuestAdyenOrderPaymentStatusInterface
{
    /**
     * @param string $orderId
     * @param string $cartId
     * @return string
     */
    public function getOrderPaymentStatus(string $orderId, string $cartId): string;
}
