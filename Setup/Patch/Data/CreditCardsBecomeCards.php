<?php

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class CreditCardsBecomeCards implements DataPatchInterface, PatchVersionInterface
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
        $this->updateCreditCardToCards($this->moduleDataSetup);
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Update the 'Credit Card' path to 'Cards'
     *
     * @param ModuleDataSetupInterface $setup
     */
    public function updateCreditCardToCards(ModuleDataSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $configTable = $setup->getTable('core_config_data');

        $oldValue = 'Credit Card';
        $newValue = 'Cards';

        $select = $connection->select()
            ->from($configTable)
            ->where(
                'path = ?',
                'payment/adyen_cc/title'
            );

        $partialPaymentConfig = $connection->fetchRow($select);

        if (!is_null($partialPaymentConfig)) {
            $connection->update(
                $configTable,
                ['value' => $newValue],
                ['value = ?' => $oldValue]
            );
        }
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
        return '8.22.5';
    }
}
