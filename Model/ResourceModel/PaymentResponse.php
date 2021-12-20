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
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PaymentResponse extends AbstractDb
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('adyen_payment_response', 'entity_id');
    }

    /**
     * Get payment response by payment id
     *
     * @param int $paymentId
     * @return array|null
     */
    public function getPaymentResponseByPaymentId(int $paymentId): ?array
    {
        $select = $this->getConnection()->select()
            ->from(['apr' => $this->getTable('adyen_payment_response')])
            ->where('apr.payment_id=?', $paymentId);

        $result = $this->getConnection()->fetchRow($select);

        return empty($result) ? null : $result;
    }


    /**
     * Get payment response by merchant reference and store id
     *
     * @param string $incrementId
     * @param int $storeId
     * @return array|null
     */
    public function getPaymentResponseByIncrementAndStoreId(string $incrementId, int $storeId): ?array
    {
        // TODO: Try to replace this with the paymentId call
        $select = $this->getConnection()->select()
            ->from(['apr' => $this->getTable('adyen_payment_response')])
            ->where('apr.merchant_reference=?', $incrementId)
            ->where('apr.store_id=?', $storeId);

        $result = $this->getConnection()->fetchRow($select);

        return empty($result) ? null : $result;
    }
}
