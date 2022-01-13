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

namespace Adyen\Payment\Model\ResourceModel\Creditmemo;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Creditmemo extends AbstractDb
{

    /**
     * Resource intialization
     */
    protected function _construct()
    {
        $this->_init('adyen_creditmemo', 'entity_id');
    }

    /**
     * Get all the adyen_creditmemo entries linked to the adyen_order_payment
     *
     * @param $adyenPaymentId
     * @return array|null
     */
    public function getAdyenCreditmemosByAdyenPaymentid($adyenPaymentId): ?array
    {
        $select = $this->getConnection()->select()
            ->from(['adyen_creditmemo' => $this->getTable('adyen_creditmemo')])
            ->where('adyen_creditmemo.adyen_order_payment_id=?', $adyenPaymentId);

        $result = $this->getConnection()->fetchAll($select);

        return empty($result) ? null : $result;
    }
}