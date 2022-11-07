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
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action;
use Magento\Framework\Filesystem\Io\File;

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
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param Config $configHelper
     * @param File $fileIo
     */
    public function __construct(
        Context $context,
        DirectoryList $directoryList,
        Config $configHelper,
        File $fileIo
    ) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->configHelper = $configHelper;
        $this->fileIo = $fileIo;
    }

    /**
     * @param $applepayUrl
     * @param $applepayPath
     * @return void
     */
    private function downloadAndUnzip($applepayUrl, $applepayPath)
    {
        $tmpPath = '/tmp/xyz';
        if (false !== file_put_contents($tmpPath, file_get_contents($applepayUrl))) {
            $zip = new \ZipArchive;
            if ($zip->open($tmpPath) === true) {
                $zip->extractTo($applepayPath);
                $zip->close();
            }
        }
    }

    /**
     * @return ResponseInterface|Redirect|Redirect&ResultInterface|ResultInterface
     * @throws FileSystemException
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
}
