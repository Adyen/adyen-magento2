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

namespace Adyen\Payment\Model\ResourceModel\Notification;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Construct
     */
    public function _construct()
    {
        $this->_init('Adyen\Payment\Model\Notification', 'Adyen\Payment\Model\ResourceModel\Notification');
    }

    /**
     * Filter the notifications table to see if there are any unprocessed ones that have been created more than 10 minutes ago
     */
    public function unprocessedNotificationsFilter()
    {
        $dateEnd = new \DateTime();
        $dateEnd->modify('-10 minute');
        $dateRange = ['to' => $dateEnd, 'datetime' => true];
        $this->addFieldToFilter('done', 0);
        $this->addFieldToFilter('processing', 0);
        $this->addFieldToFilter('created_at', $dateRange);
        return $this;
    }
}
