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

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order;

class PreAuthorizedSettingsMigration extends AbstractConfigurationSettingsMigration
{
    /**
     * Ensure that new path does not exist before updating
     *
     * @param ModuleDataSetupInterface $setup
     */
    public function updateSchemaVersion(ModuleDataSetupInterface $setup)
    {
        $path = 'payment/adyen_abstract/payment_pre_authorized';
        $existValue = Order::STATE_PENDING_PAYMENT;
        $newValue = Order::STATE_PROCESSING;
        $this->updateConfigValue($setup, $path, $existValue, $newValue);
    }

    public static function getVersion()
    {
        return '9.0.0';
    }
}
