<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api;

/**
 * Interface for querying the Adyen order payment status
 */
interface AdyenOrderPaymentStatusInterface
{
    public function getOrderPaymentStatus(string $orderId): string;
}
