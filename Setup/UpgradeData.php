<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var ReinitableConfigInterface
     */
    private $reinitableConfig;

    public function __construct(
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '2.4.4', '<')) {
            $this->updateSchemaVersion244($setup);
        }

        $setup->endSetup();
    }

    /**
     * Upgrade to 2.4.4 used for release 3.0.0
     * We use new configuration options to define if you want to store the payment for oneclick or
     * recurring or a combination of those in a more friendly way and make it easier to integrate with our checkout API
     *
     * @param ModuleDataSetupInterface $setup
     */
    public function updateSchemaVersion244(ModuleDataSetupInterface $setup)
    {
        // convert billing agreement select box to oneclick recurring settings
        $pathEnableOneclick = "payment/adyen_abstract/enable_oneclick";
        $pathEnableRecurring = "payment/adyen_abstract/enable_recurring";
        $configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($configDataTable)
            ->where(
                'path = ?',
                'payment/adyen_abstract/recurring_type'
            );
        $configRecurringTypeValues = $connection->fetchAll($select);

        foreach ($configRecurringTypeValues as $configRecurringTypeValue) {
            $scope = $configRecurringTypeValue['scope'];
            $scopeId = $configRecurringTypeValue['scope_id'];
            switch ($configRecurringTypeValue['value']) {
                case \Adyen\Payment\Model\RecurringType::ONECLICK:
                    $this->configWriter->save(
                        $pathEnableOneclick,
                        '1',
                        $scope,
                        $scopeId
                    );
                    $this->configWriter->save(
                        $pathEnableRecurring,
                        '0',
                        $scope,
                        $scopeId
                    );
                    break;
                case \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING:
                    $this->configWriter->save(
                        $pathEnableOneclick,
                        '1',
                        $scope,
                        $scopeId
                    );
                    $this->configWriter->save(
                        $pathEnableRecurring,
                        '1',
                        $scope,
                        $scopeId
                    );
                    break;
                case \Adyen\Payment\Model\RecurringType::RECURRING:
                    $this->configWriter->save(
                        $pathEnableOneclick,
                        '0',
                        $scope,
                        $scopeId
                    );
                    $this->configWriter->save(
                        $pathEnableRecurring,
                        '1',
                        $scope,
                        $scopeId
                    );
                    break;
                case \Adyen\Payment\Model\RecurringType::NONE:
                    $this->configWriter->save(
                        $pathEnableOneclick,
                        '0',
                        $scope,
                        $scopeId
                    );
                    $this->configWriter->save(
                        $pathEnableRecurring,
                        '0',
                        $scope,
                        $scopeId
                    );
                    break;
            }
        }

        // re-initialize otherwise it will cause errors
        $this->reinitableConfig->reinit();
    }
}
