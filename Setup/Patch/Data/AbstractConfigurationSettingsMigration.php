<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

abstract class AbstractConfigurationSettingsMigration implements DataPatchInterface, PatchVersionInterface
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
        $this->updateSchemaVersion($this->moduleDataSetup);
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Ensure that new path does not exist before updating
     *
     * @param ModuleDataSetupInterface $setup
     */
    abstract function updateSchemaVersion(ModuleDataSetupInterface $setup);

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
        $select = $connection->select()->from($configDataTable)->where('path = ?', $path);
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

    public function updateConfigValue(ModuleDataSetupInterface $setup, string $path, string $existValue, string $newValue): void
    {
        $config = $this->findConfig($setup, $path, $existValue);

        if (isset($config)) {
            $this->configWriter->save($path, $newValue, $config['scope'], $config['scope_id']);
        }

        // re-initialize otherwise it will cause errors
        $this->reinitableConfig->reinit();
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
}
