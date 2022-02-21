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
     *
     * @param $quoteId
     * @return array
     */
    public function getStateDataArrayWithQuoteId($quoteId)
    {
        $stateData = $this->getStateDataRowsWithQuoteId($quoteId)
            ->getFirstItem()
            ->getData(StateDataInterface::STATE_DATA);
        return !empty($stateData) ? json_decode($stateData, true) : [];
    }

    /**
     * @param $quoteId
     * @return Collection
     */
    public function getStateDataRowsWithQuoteId($quoteId)
    {
        $this->addFieldToFilter('quote_id', $quoteId);
        $this->getSelect()->order('entity_id DESC');
        return $this;
    }

    /**
     * Fetch old state data
     *
     * @return Collection
     */
    public function getExpiredStateDataRows()
    {
        $this->addFieldToFilter('updated_at', ['lt' => date('Y-m-d', strtotime('-1 day'))]);
        $this->getSelect()->order('entity_id DESC');
        return $this;
    }
}
