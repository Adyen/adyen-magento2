<?php

namespace Adyen\Payment\Model\Resource;

class Notification extends \Magento\Framework\Model\Resource\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('adyen_notification', 'entity_id');
    }

    /**
     * @desc get Notification for duplicate check
     * @param $pspReference
     * @param $eventCode
     * @param $success
     * @return array
     */
    public function getNotification($pspReference, $eventCode, $success)
    {
        $adapter = $this->getReadConnection();
        $select = $adapter->select()
            ->from(['notification' => $this->getTable('adyen_notification')])
            ->where('notification.pspreference=?', $pspReference)
            ->where('notification.event_code=?', $eventCode)
            ->where('notification.success=?', $success);
        return $adapter->fetchPairs($select);
    }

}