<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Internal;

use Magento\Quote\Api\Data\AddressInterface;

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
     * @param AddressInterface|null $billingAddress
     * @return string
     */
    public function handleInternalRequest(
        string $cartId,
        string $formKey,
        AddressInterface $billingAddress = null
    ): string;
}
