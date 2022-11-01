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

use Adyen\Payment\Model\Creditmemo;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    public function _construct()
    {
        $this->_init(\Adyen\Payment\Model\Creditmemo::class, Creditmemo::class);
    }

    /**
     * Get all the adyen_creditmemo linked to a magento invoice
     *
     * @param int $creditMemoId
     * @return array
     */
    public function getAdyenCreditMemosLinkedToMagentoInvoice(int $creditMemoId): array
    {
        $select = $this->getConnection()->select()
            ->from(['adyen_creditmemo' => $this->getTable('adyen_creditmemo')])
            ->where('adyen_creditmemo.creditmemo_id=?', $creditMemoId);

        return $this->getConnection()->fetchAll($select);
    }
}
