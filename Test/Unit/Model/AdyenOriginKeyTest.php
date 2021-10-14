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
 * Adyen Payment Module
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Helper\Data as AdyenHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class AdyenOriginKeyTest extends TestCase
{
    /** @var AdyenHelper|MockObject */
    private $helper;

    protected function setUp()
    {
        $this->helper = $this->getMockBuilder(AdyenHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /** @throws \Exception */
    public function testGetOriginKey()
    {
        $this->helper->method('getOriginKeyForBaseUrl')
            ->willReturn('something');
        $module = new AdyenOriginKey($this->helper);
        $this->assertEquals('something', $module->getOriginKey());
    }

    /** @throws \Exception */
    public function testGetOriginKeyDoesNotHandleExceptions()
    {
        $this->helper->method('getOriginKeyForBaseUrl')
            ->willThrowException(new \Exception('Some error message', 400));
        $module = new AdyenOriginKey($this->helper);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Some error message');
        $this->expectExceptionCode(400);
        $module->getOriginKey();
    }
}
