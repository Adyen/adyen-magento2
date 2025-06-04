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

use Magento\Framework\Logger\Handler\Base;
use Monolog\Formatter\LineFormatter;
use Magento\Framework\Filesystem\DriverInterface;
use Monolog\LogRecord;

class AdyenBase extends Base
{
    /**
     * AdyenBase constructor.
     *
     * @param DriverInterface $filesystem
     * @param string|null $filePath
     * @param string|null $fileName
     * @param string|null $logFormat
     */
    public function __construct(
        DriverInterface $filesystem,
        ?string $filePath = null,
        ?string $fileName = null,
        ?string $logFormat = null
    )
    {
        parent::__construct($filesystem, $filePath, $fileName);

        if ($logFormat) {
            $this->setFormatter(new LineFormatter($logFormat));
        }
    }
    /**
     * overwrite core it needs to be the exact level otherwise use different handler
     *
     * {@inheritdoc}
     */
    public function isHandling(LogRecord $record): bool
    {
        return $record['level'] == $this->level;
    }
}
