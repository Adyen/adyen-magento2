<?php

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RemoveMinMaxOrderConfigMigration implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface  $moduleDataSetup,
    )
    {
        $this->moduleDataSetup = $moduleDataSetup;
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
        $configTable = $setup->getTable('core_config_data');
        $connection->delete($configTable, ['path IN(?)' => $deletedConfigPaths]);
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
