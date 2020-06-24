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

namespace Adyen\Payment\Model\ResourceModel;

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
     * @param $originalReference
     * @param null $done
     * @return array
     */
    public function getNotification($pspReference, $eventCode, $success, $originalReference, $done = null)
    {
        $select = $this->getConnection()->select()
            ->from(['notification' => $this->getTable('adyen_notification')])
            ->where('notification.pspreference=?', $pspReference)
            ->where('notification.event_code=?', $eventCode)
            ->where('notification.success=?', $success);

        if ($done !== null) {
            $select->where('notification.done=?', $done);
        }

        if ($originalReference) {
            $select->where('notification.original_reference=?', $originalReference);
        }

        return $this->getConnection()->fetchAll($select);
    }
}
