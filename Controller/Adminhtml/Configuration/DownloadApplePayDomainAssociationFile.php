<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action;
use Magento\Framework\Filesystem\Io\File;

class DownloadApplePayDomainAssociationFile extends Action
{
    const FILE_NAME = 'apple-developer-merchantid-domain-association';
    const REMOTE_PATH = 'https://bae81f955b.cdn.adyen.com/checkoutshopper/.well-known';
    const PUB_PATH = 'pub';
    const WELL_KNOWN_PATH = '.well-known';

    public function __construct(
        private readonly Context $context,
        private readonly DirectoryList $directoryList,
        private readonly File $fileIo,
        private readonly AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface|ResponseInterface
     * @throws FileSystemException
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setUrl($this->_redirect->getRefererUrl());

        try {
            $pubPath = $this->directoryList->getPath(self::PUB_PATH);

            $this->fileIo->checkAndCreateFolder(
                sprintf("%s/%s", $pubPath, self::WELL_KNOWN_PATH),
                0700
            );

            $source = sprintf("%s/%s", self::REMOTE_PATH, self::FILE_NAME);
            $destination = sprintf("%s/%s/%s", $pubPath, self::WELL_KNOWN_PATH, self::FILE_NAME);

            $file = $this->fileIo->read($source, $destination);

            if (!$file) {
                $errorMessage =
                    __('Error while downloading Apple Pay domain association file from the remote source!');
                $this->adyenLogger->error(sprintf("%s %s", $errorMessage, $source));
                $this->messageManager->addErrorMessage($errorMessage);
            } else {
                $successMessage = __('Apple Pay domain association file has been downloaded successfully!');
                $this->adyenLogger->addAdyenDebug($successMessage);
                $this->messageManager->addSuccessMessage($successMessage);
            }
        } catch (Exception $e) {
            $errorMessage =
                __('Unknown error while downloading Apple Pay domain association file!');
            $this->adyenLogger->error(sprintf("%s %s", $errorMessage, $e->getMessage()));
            $this->messageManager->addErrorMessage($errorMessage);
        }

        return $redirect;
    }
}
