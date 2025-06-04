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

class AdyenNotification extends AdyenBase
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/adyen/notification.log';

    /**
     * @var int
     */
    protected $loggerType = Level::Notice;

    protected Level $level = Level::Notice;
}
