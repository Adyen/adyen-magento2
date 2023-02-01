<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Internal;

use Magento\Quote\Api\Data\AddressInterface;

/**
 * Interface for fetching payment methods from Adyen for logged in customers
 */
interface InternalAdyenPaymentMethodManagementInterface
{
    /**
     * Fetches Adyen payment methods for logged in customers
     */
    public function handleInternalRequest(
        string $cartId,
        string $formKey,
        AddressInterface $shippingAddress = null
    ): string;
}
