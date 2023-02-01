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
 * Interface for persisting the Adyen state data
 */
interface AdyenStateDataInterface
{
    /**
     * Persist the Adyen state data for the quote so it can be used in the payment request
     */
    public function save(string $stateData, int $quoteId): void;
}
