<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Vault;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class VaultConfigObserver implements ObserverInterface
{

    /**
     * @var Vault
     */
    private $vaultHelper;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * VaultConfigObserver constructor.
     * @param Vault $vaultHelper
     * @param Config $configHelper
     * @param WriterInterface $configWriter
     */
    public function __construct(
        Vault $vaultHelper,
        Config $configHelper,
        WriterInterface $configWriter
    ) {
        $this->vaultHelper = $vaultHelper;
        $this->configHelper = $configHelper;
        $this->configWriter = $configWriter;
    }

    /**
     * Execute when there is a change in the payment section in the admin backend (adyen config is in this section)
     *
     * The payment/adyen_cc_vault/active is required for vault to be used.
     * Whenever there's a change in the payment section, check if based on these settings vault should be active. If so,
     * set it to active, else deactivate it
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $storeId = $observer->getData('store');

        if (empty($storeId)) {
            $storeId = null;
        }

        // Get the settings based on the merchant config
        $vaultEnabledConfig = $this->vaultHelper->isCardVaultEnabled($storeId);
        // Get the value of the payment/adyen_cc_vault/active config
        $vaultActiveConfig = $this->configHelper->getConfigData('active', Config::XML_ADYEN_CC_VAULT, $storeId, true);

        // If they are not equal, update the payment/adyen_cc_vault/active config
        if ($vaultEnabledConfig !== $vaultActiveConfig) {
            $this->configWriter->save(
                'payment/' . Config::XML_ADYEN_CC_VAULT . '/active',
                intval($vaultEnabledConfig)
            );
        }
    }
}
