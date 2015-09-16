<?php

namespace Adyen\Payment\Model\Resource;

class Notification extends \Magento\Framework\Model\Resource\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('adyen_notification', 'entity_id');
    }
}