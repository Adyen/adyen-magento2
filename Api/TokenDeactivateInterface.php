<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api;

/**
 * Interface for deactivating the recurring tokens
 */
interface TokenDeactivateInterface
{
    /**
     * Deactivate a payment token.
     *
     * @param string $paymentToken
     * @param string $paymentMethodCode
     * @param int $customerId
     * @return bool
     */
    public function deactivateToken(string $paymentToken, string $paymentMethodCode, int $customerId): bool;
}
