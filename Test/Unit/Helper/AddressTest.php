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

    const HOUSE_NUMBER = '123';
    const HOUSE_NUMBER_LETTER = '456B';
    const HOUSE_NUMBER_SPACE_LETTER = '789 C';
    const HOUSE_NUMBERS = [self::HOUSE_NUMBER, self::HOUSE_NUMBER_LETTER, self::HOUSE_NUMBER_SPACE_LETTER];
    const STREET_NAME = "John-Paul's Ave.";


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
    }

    /**
     * @dataProvider addressConfigProvider
     * @param $houseNumberStreetLine
     * @param $address
     * @param $expectedResult
     * @param $streetLinesEnabled
     */
    public function testGetStreetAndHouseNumberFromAddress($houseNumberStreetLine, $address, $expectedResult, $streetLinesEnabled)
    {
        for ($i = 1; $i <= $streetLinesEnabled; $i++) {
            $this->addressAdapter->method('getStreetLine'.$i)->willReturn($address[$i-1]);
        }
        $this->addressAdapter->method('getCountryId')->willReturn("se");

        $this->assertEquals($expectedResult,
            $this->addressHelper->getStreetAndHouseNumberFromAddress(
                $this->addressAdapter,
                $houseNumberStreetLine,
                $streetLinesEnabled
            )
        );
    }

    public static function addressConfigProvider(): array
    {
        $addresses = [];
        foreach (self::HOUSE_NUMBERS as $house_number) {
            $addresses = array_merge($addresses, [
                [
                    '$houseNumberStreetLine' => 0,
                    '$address' => [self::STREET_NAME . ' ' . $house_number],
                    '$expectedResult' => [
                        'name' => self::STREET_NAME,
                        'house_number' => $house_number
                    ]
                ],
                [
                    '$houseNumberStreetLine' => 0,
                    '$address' => [$house_number . ' ' . self::STREET_NAME],
                    '$expectedResult' => [
                        'name' => self::STREET_NAME,
                        'house_number' => $house_number
                    ]
                ],
                [
                    '$houseNumberStreetLine' => 1,
                    '$address' => [
                        $house_number,
                        self::STREET_NAME
                    ],
                    '$expectedResult' => [
                        'name' => self::STREET_NAME,
                        'house_number' => $house_number
                    ]
                ],
                [
                    '$houseNumberStreetLine' => 2,
                    '$address' => [
                        self::STREET_NAME,
                        $house_number
                    ],
                    '$expectedResult' => [
                        'name' => self::STREET_NAME,
                        'house_number' => $house_number
                    ]
                ],
                [
                    '$houseNumberStreetLine' => 3,
                    '$address' => [
                        self::STREET_NAME,
                        '',
                        $house_number
                    ],
                    '$expectedResult' => [
                        'name' => self::STREET_NAME,
                        'house_number' => $house_number
                    ]
                ],
                [
                    '$houseNumberStreetLine' => 4,
                    '$address' => [
                        self::STREET_NAME,
                        '',
                        '',
                        $house_number
                    ],
                    '$expectedResult' => [
                        'name' => self::STREET_NAME,
                        'house_number' => $house_number
                    ]
                ],
            ]);
        }

        $addressConfigs = $addresses;
        for ($streetLinesEnabled = 1; $streetLinesEnabled <= 4; $streetLinesEnabled++) {
            foreach ($addressConfigs as &$addressConfig) {
                // Pad the sample address array up to the number of street lines enabled if necessary
                if ($streetLinesEnabled > count($addressConfig['$address'])) {
                    $addressConfig['$address'] = array_pad($addressConfig['$address'], $streetLinesEnabled, '');
                }
                $addressConfig['$streetLinesEnabled'] = $streetLinesEnabled;
            }
        }

        return $addressConfigs;
    }
}
