<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Repository;

interface AdyenNotificationRepositoryInterface
{
    /**
     * @param array $entityIds
     * @return void
     */
    public function deleteByIds(array $entityIds): void;
}
