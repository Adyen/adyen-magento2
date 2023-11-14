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

use Adyen\Payment\Helper\DataPatch;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Sales\Model\Order;

class CreateStatusAuthorized implements DataPatchInterface, PatchVersionInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;
    private DataPatch $dataPatchHelper;

    const ADYEN_AUTHORIZED_STATUS = 'adyen_authorized';
    const ADYEN_AUTHORIZED_STATUS_LABEL = 'Authorized';

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig,
        DataPatch $dataPatchHelper
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
        $this->dataPatchHelper = $dataPatchHelper;
    }

    public function apply()
    {
        $setup = $this->moduleDataSetup;

        $orderStateAssignmentTable = $this->moduleDataSetup->getTable('sales_order_status_state');
        $orderStatusTable = $this->moduleDataSetup->getTable('sales_order_status');

        $status = [
            [
                'status' => self::ADYEN_AUTHORIZED_STATUS,
                'label' => self::ADYEN_AUTHORIZED_STATUS_LABEL
            ]
        ];

        $this->moduleDataSetup->getConnection()->insertOnDuplicate($orderStatusTable, $status);

        $stateAssignment = [
            [
                'status' => self::ADYEN_AUTHORIZED_STATUS,
                'state' => Order::STATE_NEW,
                'is_default' => 0,
                'visible_on_front' => 1
            ]
        ];

        $this->moduleDataSetup->getConnection()->insertOnDuplicate($orderStateAssignmentTable, $stateAssignment);

        $path = 'payment/adyen_abstract/payment_pre_authorized';

        $config = $this->dataPatchHelper->findConfig($setup, $path, Order::STATE_PROCESSING);
        if (isset($config)) {
            $this->configWriter->save(
                $path,
                self::ADYEN_AUTHORIZED_STATUS,
                $config['scope'],
                $config['scope_id']
            );
        }

        // re-initialize otherwise it will cause errors
        $this->reinitableConfig->reinit();
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

    public static function getVersion(): string
    {
        return '9.0.3';
    }
}
