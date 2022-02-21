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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\Order;

use Adyen\Payment\Api\Data\OrderPaymentInterface;

class Payment extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Construct
     */
    public function _construct()
    {
        $this->_init('adyen_order_payment', 'entity_id');
    }

    /**
     * Get order payment
     *
     * @param $pspReference
     * @param $paymentId
     * @return array
     */
    public function getOrderPaymentDetails($pspReference, $paymentId)
    {
        $select = $this->getConnection()->select()
            ->from(['order_payment' => $this->getTable('adyen_order_payment')])
            ->where('order_payment.pspreference=?', $pspReference)
            ->where('order_payment.payment_id=?', $paymentId);

        $result = $this->getConnection()->fetchRow($select);

        return empty($result) ? null : $result;
    }

    /**
     * Get all the adyen_order_payment entries linked to the paymentId. Optionally filter by status
     *
     * @param $paymentId
     * @param array $statuses
     * @return array
     */
    public function getLinkedAdyenOrderPayments($paymentId, array $statuses = []): array
    {
        $select = $this->getConnection()->select()
            ->from(['order_payment' => $this->getTable('adyen_order_payment')])
            ->where('order_payment.payment_id=?', $paymentId);

        if (!empty($statuses)) {
            $select->where('order_payment.capture_status IN (?)', $statuses);
        }

        $select->order(['order_payment.created_at ASC']);

        return $this->getConnection()->fetchAll($select);
    }
}
