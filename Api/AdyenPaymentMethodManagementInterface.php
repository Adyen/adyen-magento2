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

namespace Adyen\Payment\Api;

/**
 * Interface for fetching payment methods from Adyen for logged in customers
 */
interface AdyenPaymentMethodManagementInterface
{
    /**
     * Fetches Adyen payment methods for logged in customers
     *
     * @param string $cartId
     * @param string|null $shopperLocale
     * @param string|null $country
     * @param string|null $channel
     * @return string
     */
    public function getPaymentMethods(
        string $cartId,
        ?string $shopperLocale = null,
        ?string $country = null,
        ?string $channel = null
    ) :string;
}
