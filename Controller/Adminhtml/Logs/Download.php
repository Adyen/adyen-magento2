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
use \Adyen\Payment\Helper\Data;
use \Magento\Framework\App\ProductMetadataInterface;
use \Magento\Store\Model\StoreManagerInterface;

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
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetaData;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagers;

    /**
     * Sets the log file name
     */
    private function setLogFilePath()
    {
        $this->logFilePath = $this->logsDirectory . '/' . $this->logFileName;
    }

    /**
     * Download constructor.
     * @param Context $context
     * @param Data $adyenHelper
     * @param ProductMetadataInterface $productMetadata
     * @param StoreManagerInterface $storeManagers
     */
    public function __construct(
        Context $context,
        Data $adyenHelper,
        ProductMetadataInterface $productMetadata,
        StoreManagerInterface $storeManagers
    )
    {
        $this->logsDirectory = 'var/log';
        $this->adyenHelper = $adyenHelper;
        $this->productMetaData = $productMetadata;
        $this->storeManagers = $storeManagers;
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

        $this->createCurrentApplicationInfoFile();
        $this->createArchive();
        $this->downloadArchive();

        return $resultPage;
    }

    /**
     * Downloads the created archive.
     */
    private function downloadArchive()
    {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . basename($this->logFileName));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Content-Length: ' . filesize($this->logFilePath));

        readfile($this->logFilePath);
    }

    /**
     * Creates a zip file with the content of Adyen logs.
     */
    private function createArchive()
    {
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

    /**
     * Creates a snapshot of the current configurations for Adyen module & Magento
     */
    private function createCurrentApplicationInfoFile()
    {
        $adyenPaymentSource = sprintf(
            'Adyen module version: %s',
            $this->adyenHelper->getModuleVersion()
        );

        $magentoVersion = sprintf("\nMagento version: %s", $this->productMetaData->getVersion());

        $environment = sprintf(
            "\nEnvironment: %s, with live endpoint prefix: %s",
            $this->adyenHelper->getCheckoutEnvironment(),
            $this->adyenHelper->getLiveEndpointPrefix()
        );

        $time = sprintf(
            "\nDate and time: %s",
            date('Y-m-d H:i:s')
        );

        $content = $adyenPaymentSource . $magentoVersion . $environment . $time . $this->getConfigurationValues();

        $filePath = fopen($this->logsDirectory . "/adyen/applicationInfo", "wb");
        fwrite($filePath, $content);
        fclose($filePath);
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getConfigurationValues()
    {
        $storeId = $this->storeManagers->getStore()->getId();

        $configValues = "\n\nConfiguration values: \n";

        $configs['merchantAccount'] = sprintf(
            "\nMerchant account: %s",
            $this->adyenHelper->getAdyenAbstractConfigData('merchant_account', $storeId)
        );

        $modes = $this->adyenHelper->getModes();
        $configs['mode'] = sprintf("\nMode: %s",
            $modes[$this->adyenHelper->getAdyenAbstractConfigData('demo_mode', $storeId)]
        );

        $configs['notificationUser'] = sprintf(
            "\nNotification user: %s",
            $this->adyenHelper->getAdyenAbstractConfigData('notification_username', $storeId)
        );

        $configs['clientKeyTest'] = sprintf(
            "\nClient key test: %s",
            $this->adyenHelper->getAdyenAbstractConfigData('client_key_test', $storeId)
        );

        $configs['clientKeyLive'] = sprintf(
            "\nClient key live: %s",
            $this->adyenHelper->getAdyenAbstractConfigData('client_key_live', $storeId)
        );

        $apiKeyTest = $this->adyenHelper->getAPIKey();
        if (!empty($apiKeyTest)) {
            $configs['apiKeyTest'] = sprintf(
                "\nApi key last 4: %s",
                substr($apiKeyTest, -4)
            );
        }

        foreach ($configs as $config) {
            $configValues .= $config;
        }

        return $configValues;
    }
}