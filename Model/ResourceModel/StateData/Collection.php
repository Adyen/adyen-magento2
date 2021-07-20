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
     * Search state data with quote ID or return and empty array
     *
     * @param $quoteId
     * @return []
     */
    public function getStateDataArrayWithQuoteId($quoteId)
    {
        $this->addFieldToFilter('quote_id', $quoteId);
        $this->getSelect()->order('entity_id DESC')->limit(1);
        $stateData = $this->getFirstItem()->getData(StateDataInterface::STATE_DATA);
        return !empty($stateData) ? json_decode($stateData, true) : [];
    }
}
