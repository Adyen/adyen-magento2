<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action;

class DownloadApplePayCertificate extends Action
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @param Context $context
     * @param DirectoryList $directoryList
     */
    public function __construct(
        Context              $context,
        DirectoryList        $directoryList
    ) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
    }

    /**
     * @param $applepayUrl
     * @param $applepayPath
     * @return void
     */
    private function downloadAndUnzip($applepayUrl, $applepayPath) {
        $tmpPath ='/tmp/xyz';
        if(FALSE !== file_put_contents($tmpPath,file_get_contents($applepayUrl))) {
            $zip = new \ZipArchive;
            if($zip->open($tmpPath) === TRUE){
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
        $filename = "apple-developer-merchantid-domain-association";
        $wellknownPath = $pubPath . '/' . $directoryName . '/';
        $applepayPath = $pubPath . '/' . $directoryName . '/' . $filename;
        $applepayUrl = 'https://docs.adyen.com/reuse/payment-method-pages/apple-pay/adyen-certificate/apple-developer-merchantid-domain-association.zip';
        if (!is_dir($wellknownPath )) {
            mkdir($directoryName, 0764, true);
            $this->downloadAndUnzip($applepayUrl,$wellknownPath);
        }
        else {
            chmod($directoryName,0764);
            if (!file_exists($applepayPath)) {
                $this->downloadAndUnzip($applepayUrl,$wellknownPath);
            }
        }
        return $redirect;
    }
}
