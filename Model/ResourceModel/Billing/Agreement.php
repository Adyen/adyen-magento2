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

namespace Adyen\Payment\Model\ResourceModel\Billing;

/**
 * Billing agreement resource model
 */
class Agreement extends \Magento\Paypal\Model\ResourceModel\Billing\Agreement
{
    public function getOrderRelation($agreementId, $orderId)
    {
        $select = $this->getConnection()->select()
            ->from(['billingagreement_order' => $this->getTable('paypal_billing_agreement_order')])
            ->where('billingagreement_order.agreement_id=?', $agreementId)
            ->where('billingagreement_order.order_id=?', $orderId);

        return $this->getConnection()->fetchAll($select);
    }
}
