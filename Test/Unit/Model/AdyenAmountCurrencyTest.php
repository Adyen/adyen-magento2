<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Helper\Unit\Model;

use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class AdyenAmountCurrencyTest extends AbstractAdyenTestCase
{
    /**
     * @dataProvider getCalculatedTaxPercentageProvider
     * @return void
     */
    public function testGetCalculatedTaxPercentage($amount, $currencyCode, $taxPercentage, $expectedValue)
    {
        $adyenAmountCurrency = new AdyenAmountCurrency($amount, $currencyCode, 0, $taxPercentage);

        $this->assertEquals($adyenAmountCurrency->getCalculatedTaxPercentage(), $expectedValue);
    }

    public static function getCalculatedTaxPercentageProvider()
    {
        return [
            [
                'amount' => 100.0,
                'currencyCode' => 'EUR',
                'taxAmount' => 21,
                'expectedValue' => 21
            ],
            [
                'amount' => 0,
                'currencyCode' => 'EUR',
                'taxAmount' => 21,
                'expectedValue' => 0
            ]
        ];
    }
}
