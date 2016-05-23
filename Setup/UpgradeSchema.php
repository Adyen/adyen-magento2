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

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
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
     * @param SchemaSetupInterface $setup
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
            $setup->getTable('paypal_billing_agreement'), 'agreement_data', $adyenAgreementDataColumn
        );
    }
}