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

use Monolog\Logger;
use Adyen\Payment\Logger\AdyenLogger;
use Monolog\Level;

class AdyenResult extends AdyenBase
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/adyen/result.log';

    /**
     * @var int
     */
    protected $loggerType = Level::Debug;

    protected Level $level = Level::Debug;
}
