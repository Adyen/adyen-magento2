<?php

namespace Adyen\Payment\Controller\Adminhtml\Support;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
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

    public function __construct(Config $config, Data $adyenHelper, StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
    }

    public function getConfigData(): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $notificationUsername = $this->config->getNotificationsUsername($storeId) ? 'Username set' : 'No Username';
        $notificationPassword = $this->config->getNotificationsPassword($storeId) ? 'Password set' : 'No Password';
        $merchantAccount = $this->config->getMerchantAccount($storeId);
        $environmentMode = $this->config->isDemoMode($storeId) ? 'Test' : 'Live';
        $moduleVersion = $this->adyenHelper->getModuleVersion();
        $alternativePaymentMethods = $this->config->getConfigData('active', Config::XML_ADYEN_HPP, $storeId);
        $moto = $this->config->getConfigData('active', Config::XML_ADYEN_MOTO, $storeId);

        return [
            'pluginVersion' => $moduleVersion,
            'notificationUsername' => $notificationUsername,
            'notificationPassword' => $notificationPassword,
            'merchantAccount' => $merchantAccount,
            'environmentMode' => $environmentMode,
            'paymentMethodsEnabled' => $alternativePaymentMethods,
            'motoEnabled' => $moto
        ];
    }
}
