<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\PaymentResponse;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as ResourceModel;
use Adyen\Payment\Model\PaymentResponse;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Model\Order;

class Collection extends AbstractCollection
{
    /**
     * Order states considered terminal for `adyen_payment_response` cleanup.
     */
    const FINALIZED_ORDER_STATES = [
        Order::STATE_COMPLETE,
        Order::STATE_CLOSED,
        Order::STATE_CANCELED
    ];

    public function _construct()
    {
        $this->_init(
            PaymentResponse::class,
            ResourceModel::class
        );
    }

    /**
     * Fetch the payment responses for the merchant references supplied
     *
     * @param array $merchantReferences []
     * @return array|null
     */
    public function getPaymentResponsesWithMerchantReferences($merchantReferences = [])
    {
        return $this->addFieldToFilter('merchant_reference', ["in" => [$merchantReferences]])->getData();
    }

    /**
     * Returns the `entity_id`s of payment responses whose associated Magento order has
     * reached a finalized state (complete, closed or canceled).
     *
     * The match is performed via `merchant_reference = sales_order.increment_id`, which
     * is how `TransactionPayment` stores the Magento order reference on the Adyen
     * /payments request.
     *
     * @param int $batchSize
     * @return array
     */
    public function getFinalizedPaymentResponseIds(int $batchSize): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                [PaymentResponseInterface::TABLE_NAME_ALIAS => $this->getMainTable()],
                [PaymentResponseInterface::ENTITY_ID]
            )
            ->join(
                ['so' => $this->getTable('sales_order')],
                sprintf(
                    '%s.%s = so.increment_id',
                    PaymentResponseInterface::TABLE_NAME_ALIAS,
                    PaymentResponseInterface::MERCHANT_REFERENCE
                ),
                []
            )
            ->where('so.state IN (?)', self::FINALIZED_ORDER_STATES)
            ->order(
                sprintf(
                    '%s.%s ASC',
                    PaymentResponseInterface::TABLE_NAME_ALIAS,
                    PaymentResponseInterface::ENTITY_ID
                )
            )
            ->limit($batchSize);

        return $connection->fetchCol($select);
    }

    /**
     * Returns the `entity_id`s of payment responses that have no associated Magento
     * order and are older than the given grace period in days.
     *
     * The grace period guards against deleting rows whose order is still mid-checkout
     * (e.g. action component not yet resolved).
     *
     * @param int $graceDays
     * @param int $batchSize
     * @return array
     */
    public function getOrphanPaymentResponseIds(int $graceDays, int $batchSize): array
    {
        $dateFrom = date('Y-m-d H:i:s', time() - $graceDays * 24 * 60 * 60);

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                [PaymentResponseInterface::TABLE_NAME_ALIAS => $this->getMainTable()],
                [PaymentResponseInterface::ENTITY_ID]
            )
            ->joinLeft(
                ['so' => $this->getTable('sales_order')],
                sprintf(
                    '%s.%s = so.increment_id',
                    PaymentResponseInterface::TABLE_NAME_ALIAS,
                    PaymentResponseInterface::MERCHANT_REFERENCE
                ),
                []
            )
            ->where('so.entity_id IS NULL')
            ->where(
                sprintf(
                    '%s.%s <= ?',
                    PaymentResponseInterface::TABLE_NAME_ALIAS,
                    PaymentResponseInterface::CREATED_AT
                ),
                $dateFrom
            )
            ->order(
                sprintf(
                    '%s.%s ASC',
                    PaymentResponseInterface::TABLE_NAME_ALIAS,
                    PaymentResponseInterface::ENTITY_ID
                )
            )
            ->limit($batchSize);

        return $connection->fetchCol($select);
    }
}
