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
        $this->_init(
            \Adyen\Payment\Model\Notification::class,
            \Adyen\Payment\Model\ResourceModel\Notification::class
        );
    }

    /**
     * Filter the notifications table to see if there are any unprocessed ones that have been created more than
     * 10 minutes ago
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

    /**
     * Filter the notifications table to get non processed or done notifications without 5 or more errors older than
     * 2 minutes but not older than 5 days, ordered by created_at and event_code columns
     *
     * @return $this
     */
    public function notificationsToProcessFilter()
    {
        // execute notifications from 2 minute or earlier because order could not yet been created by magento
        $dateStart = new \DateTime();
        $dateStart->modify('-5 day');
        $dateEnd = new \DateTime();
        $dateEnd->modify('-1 minute');
        $dateRange = ['from' => $dateStart, 'to' => $dateEnd, 'datetime' => true];

        $this->addFieldToFilter('done', 0);
        $this->addFieldToFilter('processing', 0);
        $this->addFieldToFilter('created_at', $dateRange);
        $this->addFieldToFilter('error_count', ['lt' => \Adyen\Payment\Model\Notification::MAX_ERROR_COUNT]);

        // Process the notifications in ascending order by creation date and event_code
        $this->getSelect()->order('created_at ASC')->order('event_code ASC');

        return $this;
    }
}
