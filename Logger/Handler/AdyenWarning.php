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

namespace Adyen\Payment\Logger\Handler;

use Adyen\Payment\Logger\AdyenLogger;
use Monolog\Level;

class AdyenWarning extends AdyenBase
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/adyen/warning.log';

    /**
     * @var int
     */
    protected $loggerType = AdyenLogger::WARNING;

    protected Level $level = Level::Warning;
}
