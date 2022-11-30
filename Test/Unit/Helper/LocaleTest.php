<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class LocaleTest extends AbstractAdyenTestCase
{
    /**
     * @var Locale
     */
    private $localeHelper;

    public function setUp(): void
    {
        $this->localeHelper =  new Locale();
    }

    public function testMapLocaleCode()
    {
        $this->assertEquals('zh-CN', $this->localeHelper->mapLocaleCode('zh_Hans_CN'));
        $this->assertEquals('zh-CN', $this->localeHelper->mapLocaleCode('zh_Hant_HK'));
        $this->assertEquals('zh-TW', $this->localeHelper->mapLocaleCode('zh_Hant_TW'));
    }
}
