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

namespace Adyen\Payment\Model\ResourceModel;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AnalyticsEvent extends AbstractDb
{
    protected function _construct()
    {
        $this->_init(
            AnalyticsEventInterface::ADYEN_ANALYTICS_EVENT,
            AnalyticsEventInterface::ENTITY_ID
        );
    }
}
