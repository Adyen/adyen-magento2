<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Setup\Patch\Data;

use Adyen\Payment\Helper\Vault;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class TokenizationSettings implements DataPatchInterface, PatchVersionInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->updateSchemaVersion821($this->moduleDataSetup);
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * For card tokens:
     * If tokenization is not enabled, do nothing
     * If vault is enabled, set the mode to vault
     * Else if adyen tokenization is enabled, set mode to Adyen Token and set Type to CardOnFile
     *
     * For alternative payment method tokens:
     * If Store alternative payment methods is on, turn the config off, since it was previously NOT operational.
     * This will ensure that if this config is turned back on, the Token type will also be saved.
     *
     * @param ModuleDataSetupInterface $setup
     */
    public function updateSchemaVersion821(ModuleDataSetupInterface $setup)
    {
        $tokenizationEnabled = $this->findConfig($setup, 'payment/adyen_oneclick/active', '1');
        $vaultEnabled = $this->findConfig($setup, 'payment/adyen_cc_vault/active', '1');
        $adyenOneClick = $this->findConfig($setup, 'payment/adyen_abstract/enable_oneclick', '1');

        if (isset($tokenizationEnabled)) {
            if (isset($vaultEnabled)) {
                $this->configWriter->save(
                    'payment/adyen_oneclick/card_mode',
                    Vault::MODE_MAGENTO_VAULT,
                    $vaultEnabled['scope'],
                    $vaultEnabled['scope_id']
                );
            } elseif (isset($adyenOneClick)) {
                $this->configWriter->save(
                    'payment/adyen_oneclick/card_mode',
                    Vault::MODE_ADYEN_TOKENIZATION,
                    $adyenOneClick['scope'],
                    $adyenOneClick['scope_id']
                );

                $this->configWriter->save(
                    'payment/adyen_oneclick/card_type',
                    Vault::CARD_ON_FILE,
                    $adyenOneClick['scope'],
                    $adyenOneClick['scope_id']
                );
            }

            $this->updateConfigValue($setup, 'payment/adyen_hpp_vault/active', '1', '0');

            // re-initialize otherwise it will cause errors
            $this->reinitableConfig->reinit();
        }
    }

    /**
     * Update a config which has a specific path and a specific value
     *
     * @param ModuleDataSetupInterface $setup
     * @param string $path
     * @param string $valueToUpdate
     * @param string $updatedValue
     */
    private function updateConfigValue(
        ModuleDataSetupInterface $setup,
        string $path,
        string $valueToUpdate,
        string $updatedValue
    ): void {
        $config = $this->findConfig($setup, $path, $valueToUpdate);
        if (isset($config)) {
            $this->configWriter->save(
                $path,
                $updatedValue,
                $config['scope'],
                $config['scope_id']
            );
        }
        // re-initialize otherwise it will cause errors
        $this->reinitableConfig->reinit();
    }

    /**
     * Return the config based on the passed path and value. If value is null, return the first item in array
     *
     * @param ModuleDataSetupInterface $setup
     * @param string $path
     * @param string|null $value
     * @return array|null
     */
    private function findConfig(ModuleDataSetupInterface $setup, string $path, ?string $value): ?array
    {
        $config = null;
        $configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($configDataTable)
            ->where(
                'path = ?',
                $path
            );

        $matchingConfigs = $connection->fetchAll($select);

        if (!empty($matchingConfigs) && is_null($value)) {
            $config = reset($matchingConfigs);
        } else {
            foreach ($matchingConfigs as $matchingConfig) {
                if ($matchingConfig['value'] === $value) {
                    $config = $matchingConfig;
                }
            }
        }

        return $config;
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    public static function getVersion()
    {
        return '8.2.1';
    }
}
