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

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Helper\CardAvailableTypes;

class CardAvailableTypesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Adyen\Payment\Helper\CardAvailableTypes
     */
    private $cardAvailableTypesHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $this->adyenHelper = $this->getMockBuilder(\Adyen\Payment\Helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adyenHelper->method("getAdyenCcTypes")
            ->willReturn([
                'AE' => [
                    'name' => 'American Express',
                    'code_alt' => 'amex'
                ],
                'VI' => [
                    'name' => 'Visa',
                    'code_alt' => 'visa'
                ],
                'MC' => [
                    'name' => 'MasterCard',
                    'code_alt' => 'mc'
                ],
                'DI' => [
                    'name' => 'Discover',
                    'code_alt' => 'discover'
                ]
            ]);
        $this->adyenHelper->method("getAdyenCcConfigData")
            ->withConsecutive(
                ['enablecctypes'],
                ['cctypes']
            )->willReturnOnConsecutiveCalls(
                1,
                'AE,VI,DI'
            ); //Intentionally missing 'MC' to assert that the method filters out that option

        $this->cardAvailableTypesHelper = new CardAvailableTypes($this->adyenHelper);

    }

    /**
     * @dataProvider cardAvailableTypesProvider
     */
    public function testGetCardAvailableTypes($index, $return)
    {
        $cardAvailableTypes = $this->cardAvailableTypesHelper->getCardAvailableTypes($index);
        $this->assertEquals($return, $cardAvailableTypes);

    }

    public function cardAvailableTypesProvider()
    {
        return array(
            array(
                'name',
                [
                    'AE' => 'American Express',
                    'VI' => 'Visa',
                    'DI' => 'Discover',
                ]

            ),
            array(
                'code_alt',
                [
                    'AE' => 'amex',
                    'VI' => 'visa',
                    'DI' => 'discover',
                ]

            )
        );
    }
}
