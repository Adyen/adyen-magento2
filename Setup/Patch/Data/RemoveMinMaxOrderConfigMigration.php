<?php

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class RemoveMinMaxOrderConfigMigration implements DataPatchInterface, PatchVersionInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;

    public function __construct(
        ModuleDataSetupInterface  $moduleDataSetup,
        WriterInterface           $configWriter,
        ReinitableConfigInterface $reinitableConfig
    )
    {
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
    public function updateSchemaVersion(ModuleDataSetupInterface $setup)
    {
        $deletedConfigPaths = [
            'payment/adyen_cc/min_order_total',
            'payment/adyen_cc/max_order_total',
            'payment/adyen_hpp/min_order_total',
            'payment/adyen_hpp/max_order_total'
        ];

        $connection = $setup->getConnection();
        $connection->delete('core_config_data', ['path IN(?)' => $deletedConfigPaths]);
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

    /**
     * @inheritDoc
     */
    public static function getVersion()
    {
        return '9.0.1';
    }
}
