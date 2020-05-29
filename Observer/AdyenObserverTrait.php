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

namespace Adyen\Payment\Observer;

trait AdyenObserverTrait
{
    /**
     * Returns an array with only the approved keys
     *
     * @param array $array
     * @param array $approvedKeys
     * @return array
     */
    private function getArrayOnlyWithApprovedKeys($array, $approvedKeys)
    {
        $result = [];

        foreach ($approvedKeys as $approvedKey) {
            if (isset($array[$approvedKey])) {
                $result[$approvedKey] = $array[$approvedKey];
            }
        }

        return $result;
    }
}