<?php

namespace Adyen\Payment\Controller\Adminhtml\Support;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigurationData
{
    /**
     * @var Config
     */
    protected Config $config;
    /**
     * @var Data
     */
    private Data $adyenHelper;
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;


    protected ProductMetadataInterface $productMetadata;

    public function __construct(Config                   $config,
                                Data                     $adyenHelper,
                                StoreManagerInterface    $storeManager,
                                ProductMetadataInterface $productMetadata)
    {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
        $this->productMetadata = $productMetadata;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId() : int
    {
        return $this->storeManager->getStore()->getId();
    }
}
