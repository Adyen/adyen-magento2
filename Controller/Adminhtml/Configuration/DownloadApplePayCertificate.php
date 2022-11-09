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

class DownloadApplePayCertificate extends Action
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var File
     */
    private $fileIo;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param Config $configHelper
     * @param File $fileIo
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Context $context,
        DirectoryList $directoryList,
        Config $configHelper,
        File $fileIo,
        AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->configHelper = $configHelper;
        $this->fileIo = $fileIo;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @return ResponseInterface|Redirect|Redirect&ResultInterface|ResultInterface
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setUrl($this->_redirect->getRefererUrl());

        $pubPath = $this->directoryList->getPath('pub');
        $directoryName = '.well-known';
        $filename = 'apple-developer-merchantid-domain-association';

        $wellknownPath = $pubPath . '/' . $directoryName;
        $applepayPath = $wellknownPath . '/' . $filename;

        $applepayUrl = $this->configHelper->getApplePayUrlPath();

        if ($this->fileIo->checkAndCreateFolder($wellknownPath, 0700)) {
            $this->downloadAndUnzip($applepayUrl, $wellknownPath);
        } else {
            $this->fileIo->chmod($wellknownPath, 0700);
            if (!$this->fileIo->fileExists($applepayPath)) {
                $this->downloadAndUnzip($applepayUrl, $wellknownPath);
            }
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
        try {
            $tmpPath = tempnam(sys_get_temp_dir(), 'apple-developer-merchantid-domain-association');
            file_put_contents($tmpPath, file_get_contents($applepayUrl));
            $zip = new \ZipArchive;
            $zip->open($tmpPath);
            $zip->extractTo($applepayPath);
            $zip->close();
        } catch (Exception $e) {
            $errormessage = 'Failed to download the ApplePay certificate please do so manually';
            $this->adyenLogger->addAdyenWarning($errormessage);
            throw new LocalizedException(__($errormessage ));
        }
    }
}
