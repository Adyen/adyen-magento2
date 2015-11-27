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

        $setup->endSetup();
    }


    public function updateSchemaVersion1001($setup)
    {
        $connection = $setup->getConnection();

        // Add column to indicate if last notification has success true or false
        $adyenNotificationEventCodeSuccessColumn = [
            'type' => Table::TYPE_BOOLEAN,
            'length' => 1,
            'nullable' => true,
            'comment' => 'Adyen Notification event code success flag'
        ];
        $connection->addColumn($setup->getTable('sales_order'), 'adyen_notification_event_code_success', $adyenNotificationEventCodeSuccessColumn);

        // add column to order_payment to save Adyen PspReference
        $pspReferenceColumn = [
            'type' => Table::TYPE_TEXT,
            'length' => 255,
            'nullable' => true,
            'comment' => 'Adyen PspReference of the payment'
        ];

        $connection->addColumn($setup->getTable('sales_order_payment'), 'adyen_psp_reference', $pspReferenceColumn);
    }
}