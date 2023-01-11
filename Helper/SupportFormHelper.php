<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Exception\FileUploadException;
use Adyen\Payment\Model\TransportBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class SupportFormHelper
{
    const MAX_ATTACHMENT_SIZE = 7000000;
    const MAX_TOTAL_SIZE = 10000000;

    /**
     * @var int
     */
    private $attachmentSize = 0;

    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Data
     */
    private $adyenHelper;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var Filesystem\Directory\WriteFactory
     */
    private $writeFactory;
    /**
     * @var Filesystem\Io\File
     */
    private $fileUtil;

    public function __construct(
        TransportBuilder $transportBuilder,
        MessageManagerInterface $messageManager,
        Config $config,
        Data $adyenHelper,
        StoreManagerInterface $storeManager,
        Filesystem\Io\File $fileUtil,
        Filesystem $filesystem,
        Filesystem\Directory\WriteFactory $writeFactory,
        ProductMetadataInterface $productMetadata
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
        $this->productMetadata = $productMetadata;
        $this->filesystem = $filesystem;
        $this->writeFactory = $writeFactory;
        $this->fileUtil = $fileUtil;
    }

    /**
     * @param array $formData
     * @param string $template
     *
     * @return void
     * @throws LocalizedException
     * @throws MailException
     * @throws FileUploadException
     */
    public function handleSubmit(array $formData, string $template): void
    {
        $storeId = $this->getStoreId();
        $formData['subject'] = '['.$formData['topic'].'] '.$formData['subject'];
        if ($this->config->isSendAdminConfigurationEnabled($storeId)) {
            $configurationData = $this->getConfigData();
            $templateVars = array_merge($configurationData, $formData);
        } else {
            $templateVars = $formData;
        }

        $templateOptions = [
            'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
            'store' => $storeId
        ];

        $to = $this->config->getSupportMailAddress($storeId);
        $from = ['email' => $templateVars['email'], 'name' => $this->config->getMerchantAccount($storeId)];
        if (!isset($from['email'])) {
            $from['email'] = $this->getGeneralContactSenderEmail();
        }

        $transportBuilder = $this->transportBuilder->setTemplateIdentifier($template)
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFromByScope($from)
            ->addTo($to);

        if (isset($formData['attachments']) && is_array($formData['attachments'])) {
            foreach ($formData['attachments'] as $file) {
                if (!empty($file['name'])) {
                    list($path, $filename) = $this->uploadAttachment($file);
                    $transportBuilder->setAttachment(file_get_contents($path), $filename);
                }
            }
        }

        $transport = $transportBuilder->getTransport();

        $transport->sendMessage();
        $this->messageManager->addSuccess(__('Form successfully submitted'));
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfigData(): array
    {
        $storeId = $this->getStoreId();
        $magentoEdition = $this->productMetadata->getEdition();
        $magentoVersion = $this->productMetadata->getVersion();
        $notificationUsername = $this->config->getNotificationsUsername($storeId) ? 'Username set' : 'No Username';
        $notificationPassword = $this->config->getNotificationsPassword($storeId) ? 'Password set' : 'No Password';
        $merchantAccount = $this->config->getMerchantAccount($storeId);
        $environmentMode = $this->config->isDemoMode($storeId) ? 'Test' : 'Live';
        $moduleVersion = $this->adyenHelper->getModuleVersion();
        $alternativePaymentMethods = $this->config->isAlternativePaymentMethodsEnabled($storeId);
        $moto = $this->config->isMotoPaymentMethodEnabled($storeId);

        $configurationMode = $this->config->getConfigurationMode($storeId);
        $isAdyenCcEnabled = $this->config->getConfigData('active', 'adyen_cc', $storeId, true);
        $isCardTokenizationEnabled = $this->getConfigData('active', 'adyen_oneclick', $storeId, true);

        return [
            'magentoEdition' => $magentoEdition,
            'magentoVersion' => $magentoVersion,
            'storeId' => $storeId,
            'pluginVersion' => $moduleVersion,
            'merchantAccount' => $merchantAccount,
            'environmentMode' => $environmentMode,
            'paymentMethodsEnabled' => $alternativePaymentMethods,
            'notificationUsername' => $notificationUsername,
            'notificationPassword' => $notificationPassword,
            'motoEnabled' => $moto
        ];
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Get the email from the general contact
     *
     * @return string
     */
    public function getGeneralContactSenderEmail(): string
    {
        $objectManager = ObjectManager::getInstance();
        $scopeConfig = $objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');

        return $scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);
    }

    private function uploadAttachment($file)
    {
        $this->attachmentSize += $file['size'];
        if ($file['size'] > self::MAX_ATTACHMENT_SIZE || $this->attachmentSize > self::MAX_TOTAL_SIZE) {
            throw new FileUploadException(
                __('Invalid file size. Each file must 7MB or less and total upload size must be 10MB or less.')
            );
        }

        $fileInfo = $this->fileUtil->getPathInfo($file['name']);
        $allowedTypes = ['zip', 'txt', 'log', 'rar', 'jpeg', 'jpg', 'pdf' ];
        if (!in_array($fileInfo['extension'], $allowedTypes)) {
            throw new FileUploadException(__('Invalid file type. Allowed types: ' . join(', ', $allowedTypes)));
        }

        $uploadDir = $this->writeFactory
            ->create($this->filesystem->getDirectoryRead(DirectoryList::TMP)->getAbsolutePath());

        $targetPath = $uploadDir->getDriver()->getRealPathSafety($uploadDir->getAbsolutePath($file['name']));

        // copy to target
        $result = $uploadDir->getDriver()->copy($file['tmp_name'], $targetPath);

        if (!$result) {
            throw new FileUploadException(__('Unable to upload attachment.'));
        }

        return [$targetPath, $file['name']];
    }

    public function requiredFieldsMissing($request, $requiredFields): string
    {
       $requiredFieldsMissing = [];
        foreach ($requiredFields as $field) {
            if (empty($request[$field])) {
                $requiredFieldsMissing[] = $field;
            }
        }
        return implode(', ', $requiredFieldsMissing);
    }
}
