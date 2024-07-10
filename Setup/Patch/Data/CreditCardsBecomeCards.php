<?php

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class CreditCardsBecomeCards implements DataPatchInterface
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
        $setup = $this->moduleDataSetup;
        $setup->getConnection()->startSetup();
        $configTable = $setup->getTable('core_config_data');

        $oldValue = 'Credit Card';
        $newValue = 'Cards';

        $select = $setup->getConnection()->select()
            ->from($configTable)
            ->where(
                'path = ?',
                'payment/adyen_cc/title'
            );

        $getRowsHavingAdyenCCPath = $setup->getConnection()->fetchRow($select);

        if (!is_null($getRowsHavingAdyenCCPath)) {
            $setup->getConnection()->update(
                $configTable,
                ['value' => $newValue],
                ['value = ?' => $oldValue]
            );
        }
        $setup->getConnection()->endSetup();
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
