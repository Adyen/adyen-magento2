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

namespace Adyen\Payment\Model\Resource;

class Notification extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * Construct
     */
    public function _construct()
    {
        $this->_init('adyen_notification', 'entity_id');
    }

    /**
     * Get Notification for duplicate check
     *
     * @param $pspReference
     * @param $eventCode
     * @param $success
     * @return array
     */
    public function getNotification($pspReference, $eventCode, $success)
    {
        $select = $this->getConnection()->select()
            ->from(['notification' => $this->getTable('adyen_notification')])
            ->where('notification.pspreference=?', $pspReference)
            ->where('notification.event_code=?', $eventCode)
            ->where('notification.success=?', $success);
        return $this->getConnection()->fetchAll($select);
    }
}