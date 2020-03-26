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

/**
 * Class UpgradeData
 * @package Adyen\Payment\Setup
 */
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
     * @var configDataTable
     */
    private $configDataTable;

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

        if (version_compare($context->getVersion(), '6.0.0', '<')) {
            $this->updateDataVersion600($setup);
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
     * Upgrade to 6.0.0
     * The new configuration UI uses new fields, combines selections that used
     * to be separated and splits old fields into multiple configurations
     *
     * @param ModuleDataSetupInterface $setup
     */
    public function updateDataVersion600(ModuleDataSetupInterface $setup)
    {

        $this->configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $this->setTerminalSelection($connection);

        $this->setKarCaptureMode($connection);

        $this->setShopperCountry($connection);

        $this->reinitableConfig->reinit();

    }

    /**
     * Sets terminal_selection configuration by checking the value of pos_store_id
     *
     * @param Magento\Framework\DB\Adapter\Pdo\Mysql $connection
     */
    private function setTerminalSelection($connection)
    {
        $pathTerminalSelection = "payment/adyen_pos_cloud/terminal_selection";
        $pathPosStoreId = "payment/adyen_pos_cloud/pos_store_id";

        //Setting terminal_selection=store_level for scopes that have pos_store_id set
        $select = $connection->select()
            ->from($this->configDataTable)
            ->where('path = ?', $pathPosStoreId)
            ->where('value <> "" AND value IS NOT NULL');
        $configPosStoreIdValues = $connection->fetchAll($select);
        foreach ($configPosStoreIdValues as $configPosStoreIdValue) {
            $scope = $configPosStoreIdValue['scope'];
            $scopeId = $configPosStoreIdValue['scope_id'];
            $this->configWriter->save(
                $pathTerminalSelection,
                'store_level',
                $scope,
                $scopeId
            );
        }

        //Setting terminal_selection=merchant_account_level for scopes where pos_store_id is empty
        $select = $connection->select()
            ->from($this->configDataTable)
            ->where('path = ?', $pathPosStoreId)
            ->where('value = ""');
        $configPosStoreIdEmptyValues = $connection->fetchAll($select);
        foreach ($configPosStoreIdEmptyValues as $configPosStoreIdEmptyValue) {
            $scopeEmpty = $configPosStoreIdEmptyValue['scope'];
            $scopeIdEmpty = $configPosStoreIdEmptyValue['scope_id'];
            $this->configWriter->save(
                $pathTerminalSelection,
                'merchant_account_level',
                $scopeEmpty,
                $scopeIdEmpty
            );
        }
    }

    /**
     * Sets kar_capture_mode configuration by checking the values of capture_on_shipment and auto_capture_openinvoice
     *
     * @param Magento\Framework\DB\Adapter\Pdo\Mysql $connection
     */
    private function setKarCaptureMode($connection)
    {
        $pathKarCaptureMode = "payment/adyen_abstract/kar_capture_mode";

        //Selecting capture_on_shipment and auto_capture_openinvoice by scope
        $select = $connection->select()
            ->from($this->configDataTable,
                [
                    'scope AS core_config_data_scope',
                    'scope_id AS core_config_data_scope_id'
                ]
            )
            ->joinLeft($this->configDataTable . ' AS capture_on_shipment',
                'capture_on_shipment.path = "payment/adyen_abstract/capture_on_shipment"
                AND capture_on_shipment.scope = core_config_data.scope
                AND capture_on_shipment.scope_id = core_config_data.scope_id',
                [
                    'value AS capture_on_shipment_value'
                ])
            ->joinLeft($this->configDataTable . ' AS auto_capture_openinvoice',
                'auto_capture_openinvoice.path = "payment/adyen_abstract/auto_capture_openinvoice"
                AND auto_capture_openinvoice.scope = core_config_data.scope
                AND auto_capture_openinvoice.scope_id = core_config_data.scope_id',
                [
                    'value AS auto_capture_openinvoice_value'
                ])
            ->where('
            core_config_data.path IN
            ("payment/adyen_abstract/capture_on_shipment", "payment/adyen_abstract/auto_capture_openinvoice")
            ');
        $configCaptureValues = $connection->fetchAll($select);

        foreach ($configCaptureValues as $configCaptureValue) {

            //If the configuration is not set we assume the default value (0)
            if ($configCaptureValue['capture_on_shipment_value'] == null) {
                $configCaptureValue['capture_on_shipment_value'] = 0;
            }
            if ($configCaptureValue['auto_capture_openinvoice_value'] == null) {
                $configCaptureValue['auto_capture_openinvoice_value'] = 0;
            }

            //Assigning values to kar_capture_mode according to source model Adyen\Payment\Model\Config\Source\KarCaptureMode
            switch ([
                $configCaptureValue['capture_on_shipment_value'],
                $configCaptureValue['auto_capture_openinvoice_value']
            ]) {
                case [1, 1]:
                case [0, 1]:
                    $karCaptureModeValue = 'capture_immediately';
                    break;
                case [1, 0]:
                    $karCaptureModeValue = 'capture_on_shipment';
                    break;
                case [0, 0]:
                    $karCaptureModeValue = 'capture_manually';
                    break;
            }

            $this->configWriter->save(
                $pathKarCaptureMode,
                $karCaptureModeValue,
                $configCaptureValue['core_config_data_scope'],
                $configCaptureValue['core_config_data_scope_id']
            );
        }
    }
    /**
     * Sets shoppercountry configuration by checking the value of payment/adyen_hpp/country_code
     *
     * @param Magento\Framework\DB\Adapter\Pdo\Mysql $connection
     */
    private function setShopperCountry($connection)
    {
        $countryCode = "payment/adyen_hpp/country_code";
        $shoppercountry = "payment/adyen_hpp/shopper_country";

        $select = $connection->select()
            ->from($this->configDataTable)
            ->where('path = ?', $countryCode)
            ->where('value <> "" AND value IS NOT NULL');
        $configCountryCodeValues = $connection->fetchAll($select);

        foreach ($configCountryCodeValues as $configCountryCodeValue) {
            $scope = $configCountryCodeValue['scope'];
            $scopeId = $configCountryCodeValue['scope_id'];

            $this->configWriter->save(
                $shoppercountry,
                1,
                $scope,
                $scopeId
            );
        }


    }
}
