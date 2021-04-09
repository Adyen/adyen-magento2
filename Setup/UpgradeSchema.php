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

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Upgrade the Catalog module DB scheme
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    const ADYEN_ORDER_PAYMENT = 'adyen_order_payment';
    const ADYEN_INVOICE = 'adyen_invoice';

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.0.1', '<')) {
            $this->updateSchemaVersion1001($setup);
        }

        if (version_compare($context->getVersion(), '1.0.0.2', '<')) {
            $this->updateSchemaVersion1002($setup);
        }

        if (version_compare($context->getVersion(), '2.0.0', '<')) {
            $this->updateSchemaVersion200($setup);
        }

        if (version_compare($context->getVersion(), '2.0.4', '<')) {
            $this->updateSchemaVersion204($setup);
        }

        if (version_compare($context->getVersion(), '2.0.7', '<')) {
            $this->updateSchemaVersion207($setup);
        }

        if (version_compare($context->getVersion(), '2.2.1', '<')) {
            $this->updateSchemaVersion221($setup);
        }

        if (version_compare($context->getVersion(), '5.4.0', '<')) {
            $this->updateSchemaVersion540($setup);
        }

        if (version_compare($context->getVersion(), '7.0.0', '<')) {
            $this->updateSchemaVersion700($setup);
        }

        $setup->endSetup();
    }

    /**
     * Upgrade to 1.0.0.1
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function updateSchemaVersion1001(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();

        // Add column to indicate if last notification has success true or false
        $adyenNotificationEventCodeSuccessColumn = [
            'type' => Table::TYPE_BOOLEAN,
            'length' => 1,
            'nullable' => true,
            'comment' => 'Adyen Notification event code success flag'
        ];

        $connection->addColumn(
            $setup->getTable('sales_order'),
            'adyen_notification_event_code_success',
            $adyenNotificationEventCodeSuccessColumn
        );

        // add column to order_payment to save Adyen PspReference
        $pspReferenceColumn = [
            'type' => Table::TYPE_TEXT,
            'length' => 255,
            'nullable' => true,
            'comment' => 'Adyen PspReference of the payment'
        ];

        $connection->addColumn($setup->getTable('sales_order_payment'), 'adyen_psp_reference', $pspReferenceColumn);
    }

    /**
     * Upgrade to 1.0.0.2
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function updateSchemaVersion1002(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();

        // Add column to indicate if last notification has success true or false
        $adyenAgreementDataColumn = [
            'type' => Table::TYPE_TEXT,
            'nullable' => true,
            'comment' => 'Agreement Data'
        ];
        $connection->addColumn(
            $setup->getTable('paypal_billing_agreement'),
            'agreement_data',
            $adyenAgreementDataColumn
        );
    }

    /**
     * Upgrade to 2.0.0
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function updateSchemaVersion200(SchemaSetupInterface $setup)
    {
        /**
         * Create table 'adyen_order_payment'
         */
        $table = $setup->getConnection()
            ->newTable($setup->getTable(self::ADYEN_ORDER_PAYMENT))
            ->addColumn(
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Adyen Payment ID'
            )
            ->addColumn(
                'pspreference',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['unsigned' => true, 'nullable' => false],
                'Pspreference'
            )
            ->addColumn(
                'merchant_reference',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['unsigned' => true, 'nullable' => false],
                'Merchant Reference'
            )
            ->addColumn(
                'payment_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => false],
                'Order Payment Id'
            )
            ->addColumn(
                'payment_method',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['unsigned' => true, 'nullable' => true],
                'Payment Method'
            )
            ->addColumn(
                'amount',
                \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                '12,4',
                ['unsigned' => true, 'nullable' => false],
                'Amount'
            )
            ->addColumn(
                'total_refunded',
                \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                '12,4',
                ['unsigned' => true, 'nullable' => false],
                'Total Refunded'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_DATETIME,
                null,
                ['nullable' => false],
                'Created at'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_DATETIME,
                null,
                ['nullable' => false],
                'Updated at'
            )
            ->addIndex(
                $setup->getIdxName(
                    self::ADYEN_ORDER_PAYMENT,
                    ['pspreference'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['pspreference'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->addForeignKey(
                $setup->getFkName(
                    self::ADYEN_ORDER_PAYMENT,
                    'payment_id',
                    'sales_order_payment',
                    'entity_id'
                ),
                'payment_id',
                $setup->getTable('sales_order_payment'),
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
            )
            ->setComment('Adyen Order Payment');

        $setup->getConnection()->createTable($table);

        // add originalReference to notification table
        $connection = $setup->getConnection();

        $column = [
            'type' => Table::TYPE_TEXT,
            'length' => 255,
            'nullable' => true,
            'comment' => 'Original Reference',
            'after' => \Adyen\Payment\Model\Notification::PSPREFRENCE
        ];

        $connection->addColumn(
            $setup->getTable('adyen_notification'),
            \Adyen\Payment\Model\Notification::ORIGINAL_REFERENCE,
            $column
        );
    }

    /**
     * Upgrade to 2.0.4
     * Update entity_id in notification from smallint to integer
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function updateSchemaVersion204(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $tableName = $setup->getTable('adyen_notification');

        $connection->changeColumn(
            $tableName,
            'entity_id',
            'entity_id',
            [
                'type' => Table::TYPE_INTEGER,
                'nullable' => false,
                'primary' => true,
                'identity' => true,
                'unsigned' => true,
                'comment' => 'Adyen Notification Entity ID'
            ]
        );
    }

    /**
     * Upgrade to 2.0.7
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function updateSchemaVersion207(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $tableName = $setup->getTable('adyen_notification');

        $adyenNotificationProcessingColumn = [
            'type' => Table::TYPE_BOOLEAN,
            'length' => 1,
            'nullable' => true,
            'default' => 0,
            'comment' => 'Adyen Notification Cron Processing',
            'after' => \Adyen\Payment\Model\Notification::DONE
        ];

        $connection->addColumn(
            $tableName,
            'processing',
            $adyenNotificationProcessingColumn
        );
    }

    public function updateSchemaVersion221(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()
            ->newTable($setup->getTable(self::ADYEN_INVOICE))
            ->addColumn(
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Adyen Invoice Entity ID'
            )
            ->addColumn(
                'pspreference',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['unsigned' => true, 'nullable' => false],
                'Adyen pspreference of the capture'
            )
            ->addColumn(
                'original_reference',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['unsigned' => true, 'nullable' => true],
                'Adyen OriginalReference of the payment'
            )
            ->addColumn(
                'acquirer_reference',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['unsigned' => true, 'nullable' => true],
                'Adyen AcquirerReference of the capture'
            )
            ->addColumn(
                'invoice_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                11,
                ['unsigned' => true, 'nullable' => false],
                'Invoice Id'
            )
            ->addForeignKey(
                $setup->getFkName(
                    self::ADYEN_INVOICE,
                    'invoice_id',
                    'sales_invoice',
                    'entity_id'
                ),
                'invoice_id',
                $setup->getTable('sales_invoice'),
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
            )
            ->setComment('Adyen Invoice');

        $setup->getConnection()->createTable($table);
    }

    /**
     * Upgrade to 5.4.0
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function updateSchemaVersion540(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $tableName = $setup->getTable('adyen_notification');

        $adyenNotificationErrorCountColumn = [
            'type' => Table::TYPE_INTEGER,
            'length' => 1,
            'nullable' => true,
            'default' => 0,
            'comment' => 'Adyen Notification Process Error Count',
            'after' => \Adyen\Payment\Model\Notification::PROCESSING
        ];

        $adyenNotificationErrorMessageColumn = [
            'type' => Table::TYPE_TEXT,
            'length' => null,
            'nullable' => true,
            'default' => null,
            'comment' => 'Adyen Notification Process Error Message',
            'after' => \Adyen\Payment\Model\Notification::ERROR_COUNT
        ];

        $connection->addColumn(
            $tableName,
            \Adyen\Payment\Model\Notification::ERROR_COUNT,
            $adyenNotificationErrorCountColumn
        );

        $connection->addColumn(
            $tableName,
            \Adyen\Payment\Model\Notification::ERROR_MESSAGE,
            $adyenNotificationErrorMessageColumn
        );
    }

    /**
     * Upgrade to 7.0.0
     *
     * New sales_order column for the currency charged based on the Adyen config option (base or display)
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function updateSchemaVersion700(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $tableName = $setup->getTable('sales_order');

        $adyenChargedCurrencyColumn = [
            'type' => Table::TYPE_TEXT,
            'length' => 255,
            'nullable' => true,
            'default' => null,
            'comment' => 'Charged currency depending on Adyen config option',
            'after' => 'adyen_notification_event_code_success'
        ];

        $connection->addColumn(
            $tableName,
            'adyen_charged_currency',
            $adyenChargedCurrencyColumn
        );
    }
}
