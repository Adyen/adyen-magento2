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

    const STREET_LINE_1 = '123 A';
    const STREET_LINE_2 = '456 B';
    const STREET_LINE_3 = '789 C';
    const STREET_LINE_4 = '012 D';
    const FULL_STREET_ARRAY = [self::STREET_LINE_1, self::STREET_LINE_2, self::STREET_LINE_3, self::STREET_LINE_4];


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

        $this->addressAdapter->method('getStreetLine1')->willReturn(self::STREET_LINE_1);
        $this->addressAdapter->method('getStreetLine2')->willReturn(self::STREET_LINE_2);
        $this->addressAdapter->method('getStreetLine3')->willReturn(self::STREET_LINE_3);
        $this->addressAdapter->method('getStreetLine4')->willReturn(self::STREET_LINE_4);

    }

    /**
     * @dataProvider configProvider
     * @param $config
     * @param $expectedResult
     */
    public function testGetStreetAndHouseNumberFromAddress($config, $expectedResult)
    {

        $this->addressAdapter->method('getCountryId')->willReturn("se");

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
                        $expectedHouseNumber = self::STREET_LINE_1;
                        break;

                    // With 2 street lines use the second line as house number (preg_match = last line)
                    case [0, 2]:
                    case [2, 2]:
                    case [3, 2]:
                    case [4, 2]:
                        $expectedStreetName = self::STREET_LINE_1;
                        $expectedHouseNumber = self::STREET_LINE_2;
                        break;

                    // With 3 street lines use the last line if house number street line is disabled or
                    // if there aren't enough street lines then default to preg_match method
                    case [0, 3]:
                    case [3, 3]:
                    case [4, 3]:
                        $expectedStreetName = implode(
                            ' ',
                            array_slice(self::FULL_STREET_ARRAY, 0, 2)
                        );
                        $expectedHouseNumber = self::STREET_LINE_3;
                        break;

                    // With 4 street lines enabled or
                    // if house number street line is disabled then use the last  (preg_match = last line)
                    case [0, 4]:
                    case [4, 4]:
                        $expectedStreetName = implode(
                            ' ',
                            array_slice(self::FULL_STREET_ARRAY, 0, 3)
                        );
                        $expectedHouseNumber = self::STREET_LINE_4;
                        break;

                    //Match individual lines to house number if there are enough street lines enabled
                    case [1, 2]:
                        $expectedStreetName = self::STREET_LINE_2;
                        $expectedHouseNumber = self::STREET_LINE_1;
                        break;
                    case [1, 3]:
                        $expectedStreetName = implode(
                            ' ',
                            array_slice(self::FULL_STREET_ARRAY, 1, 2)
                        );
                        $expectedHouseNumber = self::STREET_LINE_1;
                        break;
                    case [1, 4]:
                        $expectedStreetName = self::STREET_LINE_2 . ' ' . implode(
                                ' ',
                                array_slice(self::FULL_STREET_ARRAY, 2, 2)
                            );
                        $expectedHouseNumber = self::STREET_LINE_1;
                        break;
                    case [2, 3]:
                        $expectedStreetName = self::STREET_LINE_1 . ' ' . self::STREET_LINE_3;
                        $expectedHouseNumber = self::STREET_LINE_2;
                        break;
                    case [2, 4]:
                        $expectedStreetName = self::STREET_LINE_1 . ' ' . implode(
                                ' ',
                                array_slice(self::FULL_STREET_ARRAY, 2, 2)
                            );
                        $expectedHouseNumber = self::STREET_LINE_2;
                        break;
                    case [3, 4]:
                        $expectedStreetName = implode(
                                ' ',
                                array_slice(self::FULL_STREET_ARRAY, 0, 2)
                            ) . ' ' . self::STREET_LINE_4;
                        $expectedHouseNumber = self::STREET_LINE_3;
                        break;
                    default:
                        $expectedStreetName = implode(' ', self::FULL_STREET_ARRAY);
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
