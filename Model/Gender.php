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
    const OTHER = '3';
    const MALE_VALUE = 'MALE';
    const FEMALE_VALUE = 'FEMALE';
    const OTHER_VALUE = 'OTHER';

    /**
     * @return array
     */
    public function getGenderTypes()
    {
        return [
            self::MALE_VALUE => __('Male'),
            self::FEMALE_VALUE => __('Female'),
            self::OTHER_VALUE => __('Not specified')
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
        } elseif ($genderValue == self::OTHER_VALUE) {
            $gender = self::OTHER;
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
        } elseif ($genderValue == self::OTHER) {
            $gender = self::OTHER_VALUE;
        }
        return $gender;
    }
}
