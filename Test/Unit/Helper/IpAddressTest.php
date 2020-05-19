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

class IpAddressTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Adyen\Payment\Helper\IpAddress
     */
    private $ipAddressHelper;

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function setUp()
    {
        $cache = $this->getSimpleMock(\Magento\Framework\App\CacheInterface::class);
        $cache->method('load')->willReturn(
            array(
                '1.2.3.4',
                '20.20.20.20'
            )
        );
        $serializer = $this->getSimpleMock(\Magento\Framework\Serialize\SerializerInterface::class);
        $serializer->method('unserialize')->willReturnArgument(0);
        $ipAddressUtil = $this->getSimpleMock(\Adyen\Util\IpAddress::class);
        $adyenLogger = $this->getSimpleMock(\Adyen\Payment\Logger\AdyenLogger::class);

        $this->ipAddressHelper = new \Adyen\Payment\Helper\IpAddress(
            $ipAddressUtil,
            $cache,
            $serializer,
            $adyenLogger
        );
    }

    /**
     * @dataProvider ipAddressesProvider
     */
    public function testIsIpAddressValid($ipAddress, $expectedResult)
    {
        $this->assertEquals($expectedResult, $this->ipAddressHelper->isIpAddressValid([$ipAddress]));
    }

    public function testUpdateCachedIpAddresses()
    {
        $this->assertNull($this->ipAddressHelper->updateCachedIpAddresses());
    }

    public function testSaveIpAddressesToCache()
    {
        $this->assertNull($this->ipAddressHelper->saveIpAddressesToCache([]));
    }

    public function testGetIpAddressesFromCache()
    {
        $this->assertTrue(is_array($this->ipAddressHelper->getIpAddressesFromCache()));
    }

    public static function ipAddressesProvider()
    {
        return array(
            array(
                '1.2.3.4',
                true
            ),
            array(
                '20.20.20.20',
                true
            ),
            array(
                '8.8.8.8',
                false
            ),
            array(
                '192.168.100.10',
                false
            ),
            array(
                '500.168.100.10',
                false
            )
        );
    }
}
