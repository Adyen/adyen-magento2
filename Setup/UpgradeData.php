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

use Adyen\Payment\Helper\Config;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
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

    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig,
        Config $configHelper
    ) {
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
        $this->configHelper = $configHelper;
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

        if (version_compare($context->getVersion(), '8.0.0'. '<')) {
            $this->updateSchemaVersion800($setup);
        }

        if (version_compare($context->getVersion(), '8.2.1'. '<')) {
            $this->updateSchemaVersion821($setup);
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

    /**
     * Ensure that new path does not exist before updating
     *
     * @param ModuleDataSetupInterface $setup
     */
    public function updateSchemaVersion800(ModuleDataSetupInterface $setup)
    {
        $partialPaymentsPath = 'payment/adyen_abstract/partial_payments_refund_strategy';
        $configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($configDataTable)
            ->where(
                'path = ?',
                $partialPaymentsPath
            );

        $partialPaymentConfig = $connection->fetchRow($select);

        if (is_null($partialPaymentConfig)) {
            $connection->update(
                $configDataTable,
                ['path' => 'payment/adyen_abstract/partial_payments_refund_strategy'],
                ['path = ?' => 'payment/adyen_abstract/split_payments_refund_strategy']
            );
        }
    }

    /**
     * If Store alternative payment methods is on, turn the config off, since it was previously NOT operational.
     * This will ensure that if this config is turned back on, the Token type will also be saved.
     *
     * @param ModuleDataSetupInterface $setup
     */
    public function updateSchemaVersion821(ModuleDataSetupInterface $setup)
    {
        $configDataTable = $setup->getTable('core_config_data');
        $pathStoreAlternativePaymentMethod = 'payment/adyen_hpp_vault/active';
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($configDataTable)
            ->where(
                'path = ?',
                $pathStoreAlternativePaymentMethod
            );

        $configsStoreAlternativePaymentMethods = $connection->fetchAll($select);

        foreach ($configsStoreAlternativePaymentMethods as $config) {
            $scope = $config['scope'];
            $scopeId = $config['scope_id'];
            if ($config['value'] === '1') {
                $this->configWriter->save(
                    $pathStoreAlternativePaymentMethod,
                    '0',
                    $scope,
                    $scopeId
                );
            }
        }

        // re-initialize otherwise it will cause errors
        $this->reinitableConfig->reinit();
    }
}
