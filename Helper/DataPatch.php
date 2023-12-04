<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Framework\Setup\ModuleDataSetupInterface;

class DataPatch
{
    const CONFIG_TABLE = 'core_config_data';

    public function findConfig(
        ModuleDataSetupInterface $setup,
        string $path,
        ?string $value
    ): ?array {
        $config = null;

        $configDataTable = $setup->getTable(self::CONFIG_TABLE);
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
}
