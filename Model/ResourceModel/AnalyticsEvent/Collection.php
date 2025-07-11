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

namespace Adyen\Payment\Model\ResourceModel\AnalyticsEvent;

use Adyen\Payment\Model\AnalyticsEvent as AnalyticsEventModel;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent as AnalyticsEventResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    public function _construct()
    {
        $this->_init(
            AnalyticsEventModel::class,
            AnalyticsEventResourceModel::class
        );
    }
}
