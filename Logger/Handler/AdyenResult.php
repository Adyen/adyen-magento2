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

namespace Adyen\Payment\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Level;

class AdyenResult extends Base
{
    protected $fileName = '/var/log/adyen/result.log';
    protected $loggerType = Level::Info;
    protected Level $level = Level::Info;
}
