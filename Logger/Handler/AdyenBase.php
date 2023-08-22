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

class AdyenBase extends Base
{
    /**
     * @var string|null
     */
    private $logFormat;

    /**
     * AdyenBase constructor.
     *
     * @param DriverInterface $filesystem
     * @param string|null $filePath
     * @param string|null $fileName
     * @param string|null $logFormat
     */
    public function __construct(DriverInterface $filesystem, ?string $filePath = null, ?string $fileName = null, ?string $logFormat = null)
    {
        parent::__construct($filesystem, $filePath, $fileName);

        $this->logFormat = $logFormat;

        if ($this->logFormat) {
            $this->setFormatter(new LineFormatter($this->logFormat));
        }
    }
    /**
     * overwrite core it needs to be the exact level otherwise use different handler
     *
     * {@inheritdoc}
     */
    public function isHandling(array $record): bool
    {
        return $record['level'] == $this->level;
    }
}
