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
 * Interface for managing the Adyen state data
 */
interface AdyenStateDataInterface
{
    /**
     * Persist the Adyen state data for the quote and returns the stateDataId.
     * So it can be used in the payment request.
     *
     *
     * @param string $stateData
     * @param int $cartId
     * @return int
     */
    public function save(string $stateData, int $cartId): int;

    /**
     * Removes the Adyen state data with the given entity id
     *
     * @param int $stateDataId
     * @param int $cartId
     * @return bool
     */
    public function remove(int $stateDataId, int $cartId): bool;
}
