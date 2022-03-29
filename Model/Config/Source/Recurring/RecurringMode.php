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
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source\Recurring;

use Adyen\Payment\Helper\Recurring;
use Magento\Framework\Data\OptionSourceInterface;

class RecurringMode implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $recurringMethods = Recurring::getRecurringMethods();

        foreach ($recurringMethods as $recurringMethod) {
            $options[] = ['value' => $recurringMethod, 'label' => $recurringMethod];
        }

        return $options;
    }
}
