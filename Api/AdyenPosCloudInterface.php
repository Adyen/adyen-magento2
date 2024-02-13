<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api;

interface AdyenPosCloudInterface
{
    /**
     * Build and send donation payment request
     *
     * @param int $orderId
     * @param string $payload
     * @return void
     */
    public function pay(string $payload): void;
}
