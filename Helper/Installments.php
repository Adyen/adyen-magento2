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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

class Installments
{
    /**
     * @var array Card type bit values to indicate which ones are supported
     * e.g. In order to support AE + HIPERCARD + VI the resulting number must be 21
     */
    static $card_types = array(
        'AE' => 1,
        'ELO' => 2,
        'HIPER' => 4,
        'HIPERCARD' => 4,
        'MC' => 8,
        'VI' => 16
    );

    /**
     * @see \Adyen\Payment\Model\Config\Backend\Installments::beforeSave()
     *
     * Converts the array stored by the Installments backend model into the format required by the generic component
     *
     * @param array $installmentsArray
     * @return string
     */
    public function convertArrayToInstallmentsFormat($installmentsArray)
    {
        if (empty($installmentsArray)) {
            return '[]';
        }
        $formattedConfig = array();
        //Looping through cc_types
        foreach ($installmentsArray as $card => $cardInstallments) {
            if (isset(self::$card_types[$card])) {
                //Looping through installments configs
                foreach ($cardInstallments as $amount => $numberInstallments) {
                    //Searching the result array for matching configs
                    $configFound = array_search(array($amount, $numberInstallments), array_column($formattedConfig, 1));
                    if ($configFound !== false) {
                        //Add the card ID to the resulting ID value
                        $formattedConfig[$configFound][0] = $formattedConfig[$configFound][0] + self::$card_types[$card];

                    } else {
                        //Create a new config element
                        $formattedConfig[] = array(self::$card_types[$card], array($amount, $numberInstallments));
                    }
                }
            }
        }
        //Moving the result array values to a string with the expected format
        $strArr = [];
        foreach ($formattedConfig as $key => $config) {
            $formattedConfig[$key][] = $config[1][0];
            $formattedConfig[$key][] = $config[1][1];
            unset($formattedConfig[$key][1]);
            $strArr[] = '[' . implode(',', array($config[1][0], $config[1][1], $config[0])) . ']';
        }
        return '[' . implode(',', $strArr) . ']';
    }
}
