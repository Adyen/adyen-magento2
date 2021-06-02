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
    const ZH_HANS_CN = 'zh_Hans_CN';
    const ZH_HANT_HK = 'zh_Hant_HK';
    const ZH_HANT_TW = 'zh_Hant_TW';
    const ZH_CN = 'zh-CN';
    const ZH_TW = 'zh-TW';

    private static $chineseLocaleCodes = array(
        self::ZH_HANS_CN,
        self::ZH_HANT_HK
    );
    private static $taiwanLocaleCodes = array(
        self::ZH_HANT_TW
    );

    /**
     * Maps zh_Hans_* locale code to zh_CN
     * @param $localeCode
     * @return mixed|string
     */
    public function mapLocaleCode($localeCode)
    {
        if (in_array($localeCode, self::$chineseLocaleCodes)) {
            $localeCode = self::ZH_CN;
        } elseif (in_array($localeCode, self::$taiwanLocaleCodes)) {
            $localeCode = self::ZH_TW;
        }
        return $localeCode;
    }
}
