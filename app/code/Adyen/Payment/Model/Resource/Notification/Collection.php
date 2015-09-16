<?php

namespace Adyen\Payment\Model\Resource\Notification;

class Collection extends \Magento\Framework\Model\Resource\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init('Adyen\Payment\Model\Notification', 'Adyen\Payment\Model\Resource\Notification');
    }

}