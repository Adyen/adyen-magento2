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
 * Adyen API Library for PHP
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 */

namespace Adyen\Payment\Tests\Helper;

class InstallmentsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider installmentsConfigProvider
     */
    public function testConvertArrayToInstallmentsFormat($config, $result)
    {
        $installments = new \Adyen\Payment\Helper\Installments;
        $this->assertEquals($installments->convertArrayToInstallmentsFormat($config), $result);
    }

    public static function installmentsConfigProvider()
    {
        return array(
            array(
                array(
                    'VI' => array(1000 => '2', 2000 => '5',),
                    'MC' => array(3000 => '2', 4000 => '5',),
                    'DI' => array(3000 => '2', 4000 => '5', 2000 => '6',),
                    'UN' => array(7000 => '11',),
                    'HIPERCARD' => array(7000 => '11',),
                    'TROY' => array(7000 => '11',),
                ),
                '[[1000,2,16],[2000,5,16],[3000,2,40],[4000,5,40],[2000,6,32],[7000,11,196]]'
            ),
            array(
                array(
                    'VI' => array(100 => '2', 300 => '5'),
                    'AE' => array(100 => '2'),
                    'HIPER' => array(100 => '2'),
                    'MC' => array(100 => '2'),
                    'ELO' => array(100 => '2'),
                ),
                '[[100,2,31],[300,5,16]]'
            )
        );
    }
}
