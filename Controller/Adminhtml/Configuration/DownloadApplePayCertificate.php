<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action;
use Magento\Framework\Filesystem\Io\File;
use Exception;
use ZipArchive;

class DownloadApplePayCertificate extends Action
{
    const READ_LENGTH = 2048;
    const MAX_FILES = 10;
    const MAX_SIZE = 1000000;
    const MAX_RATIO = 5;
    const FILE_NAME = 'apple-developer-merchantid-domain-association';
    const APPLEPAY_CERTIFICATE_URL = 'https://docs.adyen.com/payment-methods/apple-pay/web-component/apple-developer-merchantid-domain-association-2024.zip';
    private $directoryList;
    private $fileIo;
    private $adyenLogger;

    public function __construct(
        Context       $context,
        DirectoryList $directoryList,
        File          $fileIo,
        AdyenLogger   $adyenLogger
    )
    {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->fileIo = $fileIo;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @return ResponseInterface|Redirect|Redirect&ResultInterface|ResultInterface
     * @throws FileSystemException
     * @throws LocalizedExceptionff
     */
    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setUrl($this->_redirect->getRefererUrl());

        $pubPath = $this->directoryList->getPath('pub');
        $directoryName = '.well-known';

        $wellknownPath = $pubPath . '/' . $directoryName;
        $applepayPath = $wellknownPath . '/' . self::FILE_NAME;

        $applepayUrl = self::APPLEPAY_CERTIFICATE_URL;

        try {
            if ($this->fileIo->checkAndCreateFolder($wellknownPath, 0700)) {
                $this->downloadAndUnzip($applepayUrl, $wellknownPath);
            } else {
                $this->fileIo->chmod($wellknownPath, 0770);
                if (!$this->fileIo->fileExists($applepayPath)) {
                    $this->downloadAndUnzip($applepayUrl, $wellknownPath);
                }
            }
        } catch (Exception $e) {
            $errormessage = 'Failed to download the ApplePay certificate, please do so manually';
            $this->adyenLogger->addAdyenWarning($errormessage);
            $this->messageManager->addErrorMessage($errormessage);
        }

        return $redirect;
    }

    /**
     * @param string $applepayUrl
     * @param string $applepayPath
     * @return void
     * @throws LocalizedException
     */
    private function downloadAndUnzip(string $applepayUrl, string $applepayPath)
    {
        $tmpPath = tempnam(sys_get_temp_dir(), self::FILE_NAME);
        file_put_contents($tmpPath, file_get_contents($applepayUrl));

        $zip = new ZipArchive;
        $fileCount = 0;
        $totalSize = 0;

        if ($zip->open($tmpPath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (self::FILE_NAME !== $filename) {
                    continue;
                }
                $stats = $zip->statIndex($i);

                // Prevent ZipSlip path traversal (S6096)
                if (strpos($filename, '../') !== false ||
                    substr($filename, 0, 1) === '/') {
                    throw new AdyenException('The zip file is trying to ZipSlip please check the file');
                }

                if (substr($filename, -1) !== '/') {
                    $fileCount++;
                    if ($fileCount > 10) {
                        // Reached max. number of files
                        throw new AdyenException('Reached max number of files please check the zip file');
                    }

                    $applepayCerticateFilestream = $zip->getStream($filename); // Compliant
                    $currentSize = 0;
                    while (!feof($applepayCerticateFilestream)) {
                        $currentSize += self::READ_LENGTH;
                        $totalSize += self::READ_LENGTH;

                        if ($totalSize > self::MAX_SIZE) {
                            // Reached max. size
                            throw new AdyenException('The file is larger than expected please check the zip file');
                        }

                        // Additional protection: check compression ratio
                        if ($stats['comp_size'] > 0) {
                            $ratio = $currentSize / $stats['comp_size'];
                            if ($ratio > self::MAX_RATIO) {
                                // Reached max. compression ratio
                                throw new AdyenException('The uncompressed file is larger than expected');
                            }
                        }
                        file_put_contents(
                            $applepayPath .'/' . $filename,
                            fread($applepayCerticateFilestream, $totalSize),
                            FILE_APPEND
                        );
                    }
                    fclose($applepayCerticateFilestream);
                }
            }
            $zip->close();
        }
    }
}
