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

namespace Adyen\Payment\Model;

class RecurringType
{
    const NONE = '';
    const ONECLICK = 'ONECLICK';
    const ONECLICK_RECURRING = 'ONECLICK,RECURRING';
    const RECURRING = 'RECURRING';

    /**
     * @var array
     */
    protected $_allowedRecurringTypesForListRecurringCall = [
        self::ONECLICK,
        self::RECURRING
    ];

    /**
     * @return array
     */
    public function getAllowedRecurringTypesForListRecurringCall()
    {
        return $this->_allowedRecurringTypesForListRecurringCall;
    }
}
