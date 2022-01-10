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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\Invoice;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    public function _construct()
    {
        $this->_init(\Adyen\Payment\Model\Invoice::class, Invoice::class);
    }

    /**
     * Get all the adyen_invoices linked to a magento invoice
     *
     * @param $invoiceId
     * @return array
     */
    public function getAdyenInvoicesLinkedToMagentoInvoice($invoiceId): array
    {
        $select = $this->getConnection()->select()
            ->from(['adyen_invoice' => $this->getTable('adyen_invoice')])
            ->where('adyen_invoice.invoice_id=?', $invoiceId);

        return $this->getConnection()->fetchAll($select);
    }
}
