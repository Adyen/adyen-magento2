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

use Adyen\Payment\Gateway\Data\Order\AddressAdapter;
use Adyen\Payment\Helper\Address;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{

    /**
     * @var Address
     */
    private $addressHelper;
    /**
     * @var AddressAdapterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $addressAdapter;

    protected function setUp(): void
    {
        $this->addressHelper = new Address();
        $this->addressAdapter = $this->getMockBuilder(AddressAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->addressAdapter->method('getStreetLine1')->willReturn('123 A');
        $this->addressAdapter->method('getStreetLine2')->willReturn('456 B');
        $this->addressAdapter->method('getStreetLine3')->willReturn('789 C');
        $this->addressAdapter->method('getStreetLine4')->willReturn('012 D');
    }

    /**
     * @dataProvider configProvider
     * @param $config
     * @param $expectedResult
     */
    public function testGetStreetAndHouseNumberFromAddress($config, $expectedResult)
    {
        $this->assertEquals($expectedResult,
            $this->addressHelper->getStreetAndHouseNumberFromAddress(
                $this->addressAdapter,
                $config['houseNumberStreetLine'],
                $config['streetLinesEnabled']
            )
        );
    }

    public static function configProvider()
    {
        $config = [];
        $i = 0;
        for ($houseNumberStreetLine = 0; $houseNumberStreetLine <= 4; $houseNumberStreetLine++) {
            for ($streetLinesEnabled = 1; $streetLinesEnabled <= 4; $streetLinesEnabled++) {
                $config[$i] = [
                    '$config' => [
                        'houseNumberStreetLine' => $houseNumberStreetLine,
                        'streetLinesEnabled' => $streetLinesEnabled,
                    ]
                ];

                switch ([$houseNumberStreetLine, $streetLinesEnabled]) {
                    // If the house number street line is disabled or
                    // if there aren't enough street lines then default to the preg_match method
                    case [0, 1]:
                    case [2, 1]:
                    case [3, 1]:
                    case [4, 1]:
                        $expectedStreetName = '123';
                        $expectedHouseNumber = 'A';
                        break;

                    // If 1 street line is enabled and house number street line is set then only return the house number
                    case [1, 1]:
                        $expectedStreetName = '';
                        $expectedHouseNumber = '123 A';
                        break;

                    // With 2 street lines use the second line as house number (preg_match = last line)
                    case [0, 2]:
                    case [2, 2]:
                    case [3, 2]:
                    case [4, 2]:
                        $expectedStreetName = '123 A';
                        $expectedHouseNumber = '456 B';
                        break;

                    // With 3 street lines use the last line if house number street line is disabled or
                    // if there aren't enough street lines then default to preg_match method
                    case [0, 3]:
                    case [3, 3]:
                    case [4, 3]:
                        $expectedStreetName = '123 A 456 B';
                        $expectedHouseNumber = '789 C';
                        break;

                    // With 4 street lines enabled or
                    // if house number street line is disabled then use the last  (preg_match = last line)
                    case [0, 4]:
                    case [4, 4]:
                        $expectedStreetName = '123 A 456 B 789 C';
                        $expectedHouseNumber = '012 D';
                        break;

                    //Match individual lines to house number if there are enough street lines enabled
                    case [1, 2]:
                        $expectedStreetName = '456 B';
                        $expectedHouseNumber = '123 A';
                        break;
                    case [1, 3]:
                        $expectedStreetName = '456 B 789 C';
                        $expectedHouseNumber = '123 A';
                        break;
                    case [1, 4]:
                        $expectedStreetName = '456 B 789 C 012 D';
                        $expectedHouseNumber = '123 A';
                        break;
                    case [2, 3]:
                        $expectedStreetName = '123 A 789 C';
                        $expectedHouseNumber = '456 B';
                        break;
                    case [2, 4]:
                        $expectedStreetName = '123 A 789 C 012 D';
                        $expectedHouseNumber = '456 B';
                        break;
                    case [3, 4]:
                        $expectedStreetName = '123 A 456 B 012 D';
                        $expectedHouseNumber = '789 C';
                        break;
                    default:
                        $expectedStreetName = '123 A 456 B 789 C 012 D';
                        $expectedHouseNumber = 'NA';
                        break;
                }

                $config[$i]['$expectedResult'] = [
                    'name' => $expectedStreetName,
                    'house_number' => $expectedHouseNumber
                ];

                $i++;
            }
        }

        return $config;
    }
}
