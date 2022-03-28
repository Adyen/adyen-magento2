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
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Logs;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;

/**
 * Class Download
 * @package Adyen\Payment\Controller\Adminhtml\Logs
 */
class Download extends \Magento\Backend\App\Action
{

    /**
     * @var
     */
    private $logsDirectory;

    /**
     * @var
     */
    private $logFileName;

    /**
     * @var
     */
    private $logFilePath;

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * Download constructor.
     * @param FileFactory $fileFactory
     * @param Context $context
     */
    public function __construct(
        FileFactory $fileFactory,
        Context $context
    )
    {
        $this->fileFactory = $fileFactory;
        $this->logsDirectory = 'var/log';
        $this->logFileName = 'M2-AdyenLogs-' . date('Y-m-d_H-i') . '.zip';

        $this->setLogFilePath();

        parent::__construct($context);
    }

    /**
     *
     * @return \Magento\Framework\View\Result\Raw
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);

        $this->createArchive();

        $this->downloadArchive();

        return $resultPage;
    }

    private function downloadArchive() {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . basename($this->logFileName));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Content-Length: ' . filesize($this->logFilePath));

        readfile($this->logFilePath);
    }

    private function createArchive() {
        $zip = new \ZipArchive();

        $zip->open($this->logFilePath , \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->logsDirectory . '/adyen'),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $filename => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filename, strlen($this->logsDirectory) + 1);

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function setLogFilePath() {
        $this->logFilePath = $this->logsDirectory . '/' . $this->logFileName;
    }
}
