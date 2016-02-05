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

namespace Adyen\Payment\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class AdyenBase extends StreamHandler
{
    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var DriverInterface
     */
    protected $filesystem;

    /**
     * @param DriverInterface $filesystem
     * @param string $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        $filePath = null
    ) {
        $this->filesystem = $filesystem;
        parent::__construct(
            $filePath ? $filePath . $this->fileName : BP . $this->fileName,
            $this->loggerType
        );
        $this->setFormatter(new LineFormatter(null, null, true));
    }

    /**
     * @{inheritDoc}
     *
     * @param $record array
     * @return void
     */
    public function write(array $record)
    {
        $logDir = $this->filesystem->getParentDirectory($this->url);
        if (!$this->filesystem->isDirectory($logDir)) {
            $this->filesystem->createDirectory($logDir, 0777);
        }

        parent::write($record);
    }

    /**
     * overwrite core it needs to be the exact level otherwise use different handler
     *
     * {@inheritdoc}
     */
    public function isHandling(array $record)
    {
        return $record['level'] == $this->level;
    }

}
