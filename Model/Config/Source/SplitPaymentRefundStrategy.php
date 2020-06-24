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

namespace Adyen\Payment\Model\Config\Source;

class SplitPaymentRefundStrategy implements \Magento\Framework\Option\ArrayInterface
{
    const REFUND_FIRST_PAYEMENT_FIRST = 1;
    const REFUND_LAST_PAYEMENT_FIRST = 2;
    const REFUND_ON_RATIO = 3;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getSplitPaymentRefundStrategies();
    }

    /**
     * @return array
     */
    public function getSplitPaymentRefundStrategies()
    {
        return [
            self::REFUND_FIRST_PAYEMENT_FIRST => __('Refund from first payment first'),
            self::REFUND_LAST_PAYEMENT_FIRST => 'Refund from last payment first',
            self::REFUND_ON_RATIO => __('refund based on ratio')
        ];
    }
}
