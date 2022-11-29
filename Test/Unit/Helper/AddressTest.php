<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Unit\Helper;

use Adyen\Payment\Gateway\Data\Order\AddressAdapter;
use Adyen\Payment\Helper\Address;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;

class AddressTest extends AbstractAdyenTestCase
{
    final const HOUSE_NUMBER = '123';
    final const HOUSE_NUMBER_LETTER = '456B';
    final const HOUSE_NUMBER_SPACE_LETTER = '789 C';
    final const HOUSE_NUMBER_SEPARATOR_LETTER = '103, 45/47 BG';
    final const HOUSE_NUMBER_RANGE = '45-53';
    final const HOUSE_NUMBERS = [
        self::HOUSE_NUMBER,
        self::HOUSE_NUMBER_LETTER,
        self::HOUSE_NUMBER_SPACE_LETTER,
        self::HOUSE_NUMBER_SEPARATOR_LETTER,
        self::HOUSE_NUMBER_RANGE
    ];
    final const STREET_NAME_SPECIAL_CHARS = "Wróblewskiego";
    final const STREET_NAME = "John-Paul's Ave.";
    final const STREET_NAME_WITH_NUMBER = "Simon 2e Carmiggeltstraat";
    final const STREET_NAMES = [
        self::STREET_NAME_SPECIAL_CHARS,
        self::STREET_NAME,
        self::STREET_NAME_WITH_NUMBER
    ];


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
        $mockLogger = $this->createMock(AdyenLogger::class);
        $this->addressHelper = new Address($mockLogger);
        $this->addressAdapter = $this->createMock(AddressAdapter::class);
    }

    /**
     * @dataProvider addressConfigProvider
     * @param $houseNumberStreetLine
     * @param $address
     * @param $expectedResult
     * @param $streetLinesEnabled
     */
    public function testGetStreetAndHouseNumberFromAddress($address, $houseNumberStreetLine, $streetLinesEnabled, $expectedResult)
    {
        /*
         * Each test case provided by the dataProvider contains an address array,
         * houseNumberStreetLine and streetLinesEnabled config options,
         * and the expected result which should be returned from getStreetAndHouseNumberFromAddress()
         */
        for ($i = 1; $i <= count($address); $i++) {
            /*
             * Set the mock addressAdapter->getStreetLine1()...getStreetLine4() methods
             * to return the corresponding item in the address array for each test case.
             */
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
        $addressConfigs = [];
        foreach (self::HOUSE_NUMBERS as $house_number) {
            foreach (self::STREET_NAMES as $street_name) {
                $addressConfigs = array_merge($addressConfigs, [
                    [
                        '$address' => [
                            $house_number,
                            $street_name
                        ],
                        '$houseNumberStreetLine' => 1,
                        '$streetLinesEnabled' => 2,
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ],
                    [
                        '$address' => [
                            $street_name,
                            $house_number
                        ],
                        '$houseNumberStreetLine' => 2,
                        '$streetLinesEnabled' => 2,
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ],
                    [
                        '$address' => [
                            $street_name,
                            '',
                            $house_number
                        ],
                        '$houseNumberStreetLine' => 3,
                        '$streetLinesEnabled' => 3,
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ],
                    [
                        '$address' => [
                            $street_name,
                            '',
                            '',
                            $house_number
                        ],
                        '$houseNumberStreetLine' => 4,
                        '$streetLinesEnabled' => 4,
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ],
                    /*
                     * The following test case is an example of misconfiguration;
                     * houseNumberStreetLine is set (non-zero) but full address is provided in house number field
                     */
                    [
                        '$address' => [$street_name . ' ' . $house_number, ''],
                        '$houseNumberStreetLine' => 1,
                        '$streetLinesEnabled' => 2,
                        '$expectedResult' => [
                            'name' => '',
                            'house_number' => $street_name . ' ' . $house_number
                        ]
                    ],
                    /* The following test cases will use the regex fallback to detect the house number and street name */
                    [
                        '$address' => [$street_name . ' ' . $house_number, ''],
                        '$houseNumberStreetLine' => 0, // Config is disabled
                        '$streetLinesEnabled' => 2,
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ],
                    [
                        '$address' => [$house_number . ' ' . $street_name, ''],
                        '$houseNumberStreetLine' => 0, // Config is disabled
                        '$streetLinesEnabled' => 2,
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ],
                    [
                        '$address' => [$street_name . ' ' . $house_number],
                        '$houseNumberStreetLine' => 2,
                        '$streetLinesEnabled' => 1, // Not enough street lines enabled
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ],
                    [
                        '$address' => [$house_number . ' ' . $street_name], // House number field is empty
                        '$houseNumberStreetLine' => 2,
                        '$streetLinesEnabled' => 2,
                        '$expectedResult' => [
                            'name' => $street_name,
                            'house_number' => $house_number
                        ]
                    ]
                ]);
            }
        }

        return $addressConfigs;
    }
}
