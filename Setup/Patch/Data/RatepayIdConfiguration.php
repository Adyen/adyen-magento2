<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
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

class RatepayIdConfiguration implements DataPatchInterface
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

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->updateConfigValue(
            $this->moduleDataSetup,
            'payment/adyen_hpp/ratepay_id',
            'payment/adyen_ratepay/ratepay_id'
        );

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function updateConfigValue(
        ModuleDataSetupInterface $setup,
        string $oldPath,
        string $newPath
    ): void {
        $config = $this->findConfig($setup, $oldPath);

        if ($config !== false) {
            $this->configWriter->save(
                $newPath,
                $config['value'],
                $config['scope'],
                $config['scope_id']
            );
        }

        $this->reinitableConfig->reinit();
    }

    private function findConfig(ModuleDataSetupInterface $setup, string $path): mixed
    {
        $configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($configDataTable)
            ->where(
                'path = ?',
                $path
            );

        $matchingConfigs = $connection->fetchAll($select);
        return reset($matchingConfigs);
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }
}
