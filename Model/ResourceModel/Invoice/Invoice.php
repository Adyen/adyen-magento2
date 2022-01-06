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

use Adyen\Payment\Model\Notification;
use Adyen\Payment\Setup\UpgradeSchema;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Sales\Model\Order;

class Invoice extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('adyen_invoice', 'entity_id');
    }

    /**
     * Get all the adyen_invoice entries linked to the adyen_order_payment
     *
     * @param $adyenPaymentId
     * @return array|null
     */
    public function getAdyenInvoicesByAdyenPaymentId($adyenPaymentId): ?array
    {
        $select = $this->getConnection()->select()
            ->from(['adyen_invoice' => $this->getTable('adyen_invoice')])
            ->where('adyen_invoice.adyen_order_payment_id=?', $adyenPaymentId);

        $result = $this->getConnection()->fetchAll($select);

        return empty($result) ? null : $result;
    }

    /**
     * Get the respective adyen_invoice entry by using the pspReference of the original payment, the pspReference of the capture
     * and the magento payment_id linked to this order
     *
     * @param Order $order
     * @param Notification $notification
     * @return array|null
     */
    public function getAdyenInvoiceByCaptureWebhook(Order $order, Notification $notification): ?array
    {
        $select = $this->getConnection()->select()
            ->from(['adyen_invoice' => $this->getTable('adyen_invoice')])
            ->joinInner(
                ['aop' => 'adyen_order_payment'],
                'aop.entity_id = adyen_invoice.adyen_order_payment_id'
            )
            ->where('aop.payment_id=?', $order->getPayment()->getEntityId())
            ->where('adyen_invoice.pspReference=?', $notification->getPspreference())
            ->where('aop.pspReference=?', $notification->getOriginalReference())
            ->columns('adyen_invoice.*');

        $result = $this->getConnection()->fetchRow($select);

        return empty($result) ? null : $result;
    }
}
