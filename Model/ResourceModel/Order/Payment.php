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
     * Get all the adyen_order_payment entries linked to the paymentId
     *
     * @param $paymentId
     * @return array|null
     */
    public function getLinkedAdyenOrderPayments($paymentId)
    {
        $select = $this->getConnection()->select()
            ->from(['order_payment' => $this->getTable('adyen_order_payment')])
            ->where('order_payment.payment_id=?', $paymentId);

        $result = $this->getConnection()->fetchAll($select);

        return empty($result) ? null : $result;
    }
}
