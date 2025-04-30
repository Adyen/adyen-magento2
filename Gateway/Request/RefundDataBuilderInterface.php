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

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

interface RefundDataBuilderInterface extends BuilderInterface
{
    const REFUND_STRATEGY_ASCENDING_ORDER = '1';
    const REFUND_STRATEGY_DESCENDING_ORDER = '2';
    const REFUND_STRATEGY_BASED_ON_RATIO = '3';
}
