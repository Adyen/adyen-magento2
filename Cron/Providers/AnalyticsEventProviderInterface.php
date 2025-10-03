<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron\Providers;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;

interface AnalyticsEventProviderInterface
{
    const BATCH_SIZE = 1000;

    /**
     * @return AnalyticsEventInterface[]
     */
    public function provide(): array;

    /**
     * @return string
     */
    public function getAnalyticsContext(): string;

    /**
     * @return string
     */
    public function getProviderName(): string;
}
