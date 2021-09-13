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

use Adyen\Payment\Model\ResourceModel\Order\Payment;

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
     * Get the total amount of the linked adyen_order_payments. Only get captured ones based on the flag
     *
     * @param integer $paymentId
     * @return array
     */
    public function getTotalAmount($paymentId, $captured = false)
    {
        $connection = $this->getConnection();

        $sumCond = new \Zend_Db_Expr(
            "SUM(adyen_order_payment.{$connection->quoteIdentifier(\Adyen\Payment\Model\Order\Payment::AMOUNT)})"
        );

        if ($captured) {
            $whereClause = 'payment_id = :payment_id AND (capture_status = :auto_capture OR capture_status = :manual_capture)';
            $whereParams = [
                ':payment_id' => $paymentId,
                ':auto_capture' => \Adyen\Payment\Model\Order\Payment::CAPTURE_STATUS_AUTO_CAPTURE,
                ':manual_capture' => \Adyen\Payment\Model\Order\Payment::CAPTURE_STATUS_MANUAL_CAPTURE,
            ];
        } else {
            $whereClause = 'payment_id = :payment_id';
            $whereParams = [
                ':payment_id' => $paymentId
            ];
        }

        $select = $connection->select()->from(
            ['adyen_order_payment' => $this->getTable('adyen_order_payment')],
            ['total_amount' => $sumCond]
        )->where($whereClause);

        return $connection->fetchAll($select, $whereParams);
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
