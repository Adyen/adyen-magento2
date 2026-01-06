<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AnalyticsEvent extends AbstractDb
{
    protected function _construct()
    {
        $this->_init(
            AnalyticsEventInterface::ADYEN_ANALYTICS_EVENT,
            AnalyticsEventInterface::ENTITY_ID
        );
    }

    /**
     * Deletes the rows corresponding to the given `entity_id`s
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

        $tableName = $this->getMainTable();

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from([AnalyticsEventInterface::TABLE_NAME_ALIAS => $tableName])
            ->where(
                sprintf(
                    "%s.%s IN (?)",
                    AnalyticsEventInterface::TABLE_NAME_ALIAS,
                    AnalyticsEventInterface::ENTITY_ID
                ),
                $entityIds
            );

        $connection->query($select->deleteFromSelect(AnalyticsEventInterface::TABLE_NAME_ALIAS));
    }
}
