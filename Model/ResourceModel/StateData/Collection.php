<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\StateData;

use Adyen\Payment\Api\Data\StateDataInterface;
use Adyen\Payment\Model\ResourceModel\StateData as ResourceModel;
use Adyen\Payment\Model\StateData;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    public function _construct()
    {
        $this->_init(
            StateData::class,
            ResourceModel::class
        );
    }

    /**
     * Fetch the most recent state data with quote ID or return an empty array
     */
    public function getStateDataArrayWithQuoteId(int $quoteId): array
    {
        $stateData = $this->getStateDataRowsWithQuoteId($quoteId)
            ->getFirstItem()
            ->getData(StateDataInterface::STATE_DATA);
        return !empty($stateData) ? json_decode((string) $stateData, true) : [];
    }

    public function getStateDataRowsWithQuoteId(int $quoteId, string $sorting = 'DESC'): Collection
    {
        $this->addFieldToFilter('quote_id', $quoteId);
        $this->getSelect()->order("entity_id $sorting");
        return $this;
    }

    /**
     * Fetch old state data
     */
    public function getExpiredStateDataRows(): Collection
    {
        $this->addFieldToFilter('updated_at', ['lt' => date('Y-m-d', strtotime('-1 day'))]);
        $this->getSelect()->order('entity_id DESC');
        return $this;
    }
}
