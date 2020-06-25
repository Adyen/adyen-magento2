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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\Order\Payment;

/**
 * Billing agreements resource collection
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Collection initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Adyen\Payment\Model\Order\Payment::class, \Adyen\Payment\Model\ResourceModel\Order\Payment::class);
    }

    /**
     * @param integer $paymentId
     * @return array
     */
    public function getTotalAmount($paymentId)
    {
        $connection = $this->getConnection();

        $sumCond = new \Zend_Db_Expr(
            "SUM(adyen_order_payment.{$connection->quoteIdentifier(\Adyen\Payment\Model\Order\Payment::AMOUNT)})"
        );

        $select = $connection->select()->from(
            ['adyen_order_payment' => $this->getTable('adyen_order_payment')],
            ['total_amount' => $sumCond]
        )->where(
            'payment_id = :payment_id'
        );

        return $connection->fetchAll($select, [':payment_id' => $paymentId]);
    }

    /**
     * @param string $paymentId
     * @return $this
     */
    public function addPaymentFilterAscending($paymentId)
    {
        $this->addFieldToFilter('payment_id', $paymentId);
        $this->getSelect()->order(['created_at ASC']);
        return $this;
    }

    /**
     * @param string $paymentId
     * @return $this
     */
    public function addPaymentFilterDescending($paymentId)
    {
        $this->addFieldToFilter('payment_id', $paymentId);
        $this->getSelect()->order(['created_at DESC']);
        return $this;
    }
}
