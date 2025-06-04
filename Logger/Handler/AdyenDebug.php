<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Logger\Handler;

use Adyen\Payment\Logger\AdyenLogger;
use Monolog\Logger;
use Monolog\Level;

class AdyenDebug extends AdyenBase
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/adyen/debug.log';

    /**
     * @var int
     */
    protected $loggerType = Level::Debug;

    protected Level $level = Level::Debug;
}
