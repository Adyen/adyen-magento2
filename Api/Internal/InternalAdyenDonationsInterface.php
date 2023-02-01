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

namespace Adyen\Payment\Api\Internal;

interface InternalAdyenDonationsInterface
{
    /**
     * Build and send internal donation payment request
     */
    public function handleInternalRequest(string $payload, string $formKey): void;
}
