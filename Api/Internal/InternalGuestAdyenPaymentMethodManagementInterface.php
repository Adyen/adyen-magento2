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

/**
 * Interface for fetching payment methods from Adyen for guest customers
 *
 * @api
 */
interface InternalGuestAdyenPaymentMethodManagementInterface
{
    /**
     * Fetches Adyen payment methods for guest customers
     *
     * @param string $cartId
     * @param string $formKey
     * @return string
     */
    public function handleInternalRequest(string $cartId, string $formKey): string;
}
