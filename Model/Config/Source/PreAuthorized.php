<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Magento\Sales\Model\Order;

/**
 * Order Statuses source model
 */
class PreAuthorized extends \Magento\Sales\Model\Config\Source\Order\Status
{
    const STATE_ADYEN_AUTHORIZED = 'adyen_authorized';

    /**
     * @var string[]
     */
    protected $_stateStatuses = [
        Order::STATE_NEW,
        self::STATE_ADYEN_AUTHORIZED
    ];
}
