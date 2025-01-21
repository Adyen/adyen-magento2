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

namespace Adyen\Payment\Cron\Providers;

interface NotificationsProviderInterface
{
    /**
     * @return array
     */
    public function provide(): array;

    /**
     * @return string
     */
    public function getProviderName(): string;
}
