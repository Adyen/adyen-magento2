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
 * Interface for querying the Adyen order payment status
 */
interface InternalAdyenOrderPaymentStatusInterface
{
    /**
     * @param string $orderId
     * @param string $formKey
     * @return string
     */
    public function handleInternalRequest(string $orderId, string $formKey): string;
}
