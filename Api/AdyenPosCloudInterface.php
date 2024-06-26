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
     * @param int $orderId
     * @return void
     */
    public function pay(int $orderId): void;
}
