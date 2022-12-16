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

use Adyen\Payment\Model\TransportBuilder;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class SupportFormHelper
{
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

    public function __construct(
        TransportBuilder         $transportBuilder,
        MessageManagerInterface  $messageManager,
        Config                   $config,
        Data                     $adyenHelper,
        StoreManagerInterface    $storeManager,
        ProductMetadataInterface $productMetadata
    )
    {
        $this->transportBuilder = $transportBuilder;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
        $this->productMetadata = $productMetadata;
    }

    /**
     * @param array $formData
     * @param string $template
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
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

        $attachmentBody = null;
        $attachmentFilename = null;

        if (isset($formData['attachment'])) {
            $attachmentBody = $formData['attachment']; // todo move uploaded file
            $attachmentFilename = 'attachment.txt';
        }

        $transport = $this->transportBuilder->setTemplateIdentifier($template)
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFromByScope($from)
            ->addTo($to)
            ->setAttachment($attachmentBody, $attachmentFilename)
            ->getTransport();
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
        $alternativePaymentMethods = $this->config->getConfigData('active', Config::XML_ADYEN_HPP, $storeId);
        $moto = $this->config->getConfigData('active', Config::XML_ADYEN_MOTO, $storeId);

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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
        return $scopeConfig->getValue('trans_email/ident_general/email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
