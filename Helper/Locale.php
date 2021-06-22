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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

/**
 * Class Locale
 * @package Adyen\Payment\Helper
 */
class Locale
{

    private static $localeMappings = array(
        'zh_Hans_CN' => 'zh-CN',
        'zh_Hant_HK' => 'zh-CN',
        'zh_Hant_TW' => 'zh-TW'
    );

    /**
     * @param $localeCode
     * @return mixed|string
     */
    public function mapLocaleCode($localeCode)
    {
        return !empty(self::$localeMappings[$localeCode]) ? self::$localeMappings[$localeCode] : $localeCode;
    }
}
