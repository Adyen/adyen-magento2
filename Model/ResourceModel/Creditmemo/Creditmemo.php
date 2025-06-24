<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\Creditmemo;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Creditmemo extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('adyen_creditmemo', 'entity_id');
    }

    /**
     * @param string $pspreference
     * @return string
     * @throws LocalizedException
     */
    public function getIdByPspreference(string $pspreference): string
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from($this->getMainTable(), 'entity_id')
            ->where('pspreference = :pspreference');

        $bind = [':pspreference' => $pspreference];

        return $connection->fetchOne($select, $bind);
    }
}
