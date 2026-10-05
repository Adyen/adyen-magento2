<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PaymentResponse extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PaymentResponseInterface::TABLE_NAME, PaymentResponseInterface::ENTITY_ID);
    }

    /**
     * Deletes the rows corresponding to the given `entity_id`s.
     *
     * @param array $entityIds
     * @return void
     * @throws LocalizedException
     */
    public function deleteByIds(array $entityIds): void
    {
        if (empty($entityIds)) {
            return;
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from([PaymentResponseInterface::TABLE_NAME_ALIAS => $this->getMainTable()])
            ->where(
                sprintf(
                    '%s.%s IN (?)',
                    PaymentResponseInterface::TABLE_NAME_ALIAS,
                    PaymentResponseInterface::ENTITY_ID
                ),
                $entityIds
            );

        $connection->query($select->deleteFromSelect(PaymentResponseInterface::TABLE_NAME_ALIAS));
    }
}
