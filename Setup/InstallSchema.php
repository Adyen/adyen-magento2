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

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use \Magento\Framework\DB\Adapter\AdapterInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        $table = $installer->getConnection()
            ->newTable($installer->getTable('adyen_notification'))
            ->addColumn(
                'entity_id',
                Table::TYPE_SMALLINT,
                null,
                [
                    'identity' => true,
                    'nullable' => false,
                    'primary' => true
                ],
                'Entity ID'
            )
            ->addColumn('pspreference', Table::TYPE_TEXT, 255, ['nullable' => true], 'Pspreference')
            ->addColumn('merchant_reference', Table::TYPE_TEXT, 255, ['nullable' => true], 'Merchant Reference')
            ->addColumn('event_code', Table::TYPE_TEXT, 255, ['nullable' => true], 'Event Code')
            ->addColumn('success', Table::TYPE_TEXT, 255, ['nullable' => true], 'Success')
            ->addColumn('payment_method', Table::TYPE_TEXT, 255, ['nullable' => true], 'Payment Method')
            ->addColumn('amount_value', Table::TYPE_TEXT, 255, ['nullable' => true], 'Amount value')
            ->addColumn('amount_currency', Table::TYPE_TEXT, 255, ['nullable' => true], 'Amount currency')
            ->addColumn('reason', Table::TYPE_TEXT, 255, ['nullable' => true], 'reason')
            ->addColumn('live', Table::TYPE_TEXT, 255, ['nullable' => true], 'Send from Live platform of adyen?')
            ->addColumn('additional_data', Table::TYPE_TEXT, null, ['nullable' => true], 'AdditionalData')
            ->addColumn('done', Table::TYPE_BOOLEAN, null, ['nullable' => false, 'default' => 0], 'done')
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                [
                    'nullable' => false,
                    'default' => Table::TIMESTAMP_INIT
                ],
                'Created At'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                [
                    'nullable' => false,
                    'default' => Table::TIMESTAMP_INIT_UPDATE
                ],
                'Updated At'
            )
            ->addIndex($installer->getIdxName('adyen_notification', ['pspreference']), ['pspreference'])
            ->addIndex($installer->getIdxName('adyen_notification', ['event_code']), ['event_code'])
            ->addIndex(
                $installer->getIdxName(
                    'adyen_notification',
                    ['pspreference', 'event_code'],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['pspreference', 'event_code'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->addIndex(
                $installer->getIdxName(
                    'adyen_notification',
                    ['merchant_reference', 'event_code'],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['merchant_reference', 'event_code'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->setComment('Adyen Notifications');

        $installer->getConnection()->createTable($table);

        $orderTable = $installer->getTable('sales_order');

        $columns = [
            'adyen_resulturl_event_code' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 255,
                'nullable' => true,
                'comment' => 'Adyen resulturl event status',
            ],
            'adyen_notification_event_code' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 255,
                'nullable' => true,
                'comment' => 'Adyen notification event status',
            ]
        ];

        $connection = $installer->getConnection();
        foreach ($columns as $name => $definition) {
            $connection->addColumn($orderTable, $name, $definition);
        }

        $installer->endSetup();
    }
}
