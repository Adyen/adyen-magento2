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
 * Interface for performing an Adyen payment details call
 */
interface InternalAdyenPaymentDetailsInterface
{
    /**
     * @param string $payload
     * @param string $formKey
     * @return string
     */
    public function handleInternalRequest(string $payload, string $formKey): string;
}
