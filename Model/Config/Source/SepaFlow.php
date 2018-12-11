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

class SepaFlow implements \Magento\Framework\Option\ArrayInterface
{
    const SEPA_FLOW_SALE = 'sale';
    const SEPA_FLOW_AUTHCAP = 'authcap';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::SEPA_FLOW_SALE, 'label' => __('Sale')],
            ['value' => self::SEPA_FLOW_AUTHCAP, 'label' => __('Auth/Cap')],
        ];
    }
}
