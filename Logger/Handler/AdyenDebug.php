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

class AdyenDebug extends Base
{
    protected $fileName = '/var/log/adyen/debug.log';
    protected $loggerType = Level::Debug;
    protected Level $level = Level::Debug;
}
