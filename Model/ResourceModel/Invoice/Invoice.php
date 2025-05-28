<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\Invoice;

use Adyen\Payment\Api\Data\InvoiceInterface;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Sales\Model\Order;

class Invoice extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('adyen_invoice', 'entity_id');
    }

    /**
     * Gets the entity_id of the adyen_invoice by the given `pspreference`
     *
     * @param string $pspreference
     * @return string
     * @throws LocalizedException
     */
    public function getIdByPspreference(string $pspreference): string
    {
        $connection = $this->getConnection();

        $select = $connection->select()
            ->from($this->getMainTable(), InvoiceInterface::ENTITY_ID)
            ->where('pspreference = :pspreference');

        $bind = [':pspreference' => $pspreference];

        return $connection->fetchOne($select, $bind);
    }
}
