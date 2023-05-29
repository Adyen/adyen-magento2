<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Util;

class DataArrayValidator
{
    /**
     * Returns an array with only the approved keys
     *
     * @param array $array
     * @param array $approvedKeys
     * @return array
     */
    public static function getArrayOnlyWithApprovedKeys($array, $approvedKeys)
    {
        $result = array();

        foreach ($approvedKeys as $approvedKey) {
            if (isset($array[$approvedKey])) {
                $result[$approvedKey] = $array[$approvedKey];
            }
        }
        return $result;
    }
}
