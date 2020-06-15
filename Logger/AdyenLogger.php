<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Logger;

use Monolog\Logger;

class AdyenLogger extends Logger
{
    /**
     * Detailed debug information
     */
    const ADYEN_DEBUG = 101;
    const ADYEN_NOTIFICATION = 201;
    const ADYEN_RESULT = 202;
    const ADYEN_NOTIFICATION_CRONJOB = 203;

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     * Overrule the default to add Adyen specific loggers to log into seperate files
     *
     * @var array $levels Logging levels
     */
    protected static $levels = [
        100 => 'DEBUG',
        101 => 'ADYEN_DEBUG',
        200 => 'INFO',
        201 => 'ADYEN_NOTIFICATION',
        202 => 'ADYEN_RESULT',
        203 => 'ADYEN_NOTIFICATION_CRONJOB',
        250 => 'NOTICE',
        300 => 'WARNING',
        400 => 'ERROR',
        500 => 'CRITICAL',
        550 => 'ALERT',
        600 => 'EMERGENCY',
    ];

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addAdyenNotification($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_NOTIFICATION, $message, $context);
    }

    public function addAdyenDebug($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_DEBUG, $message, $context);
    }

    public function addAdyenResult($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_RESULT, $message, $context);
    }

    public function addAdyenNotificationCronjob($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_NOTIFICATION_CRONJOB, $message, $context);
    }

    /**
     * Adds a log record.
     *
     * @param integer $level The logging level
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [])
    {
        $context['is_exception'] = $message instanceof \Exception;
        return parent::addRecord($level, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addNotificationLog($message, array $context = [])
    {
        return $this->addRecord(static::INFO, $message, $context);
    }
}
