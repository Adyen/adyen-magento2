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

class Gender
{
    const MALE = '1';
    const FEMALE = '2';
    const MALE_VALUE = 'MALE';
    const FEMALE_VALUE = 'FEMALE';

    /**
     * @return array
     */
    public function getGenderTypes()
    {
        return [
            self::MALE_VALUE => __('Male'),
            self::FEMALE_VALUE => __('Female')
        ];
    }

    /**
     * Get Magento Gender Value from Adyen Gender Value
     *
     * @param string $genderValue
     * @return null|string
     */
    public function getMagentoGenderFromAdyenGender($genderValue)
    {
        $gender = null;
        if ($genderValue == self::MALE_VALUE) {
            $gender = self::MALE;
        } elseif ($genderValue == self::FEMALE_VALUE) {
            $gender = self::FEMALE;
        }
        return $gender;
    }

    /**
     * @param string $genderValue
     * @return null|string
     */
    public function getAdyenGenderFromMagentoGender($genderValue)
    {
        $gender = null;
        if ($genderValue == self::MALE) {
            $gender = self::MALE_VALUE;
        } elseif ($genderValue == self::FEMALE) {
            $gender = self::FEMALE_VALUE;
        }
        return $gender;
    }
}
