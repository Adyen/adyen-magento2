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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\IpAddress;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

class IpAddressTest extends AbstractAdyenTestCase
{
    /**
     * @var IpAddress
     */
    private $ipAddressHelper;

    protected function setUp(): void
    {
        $cache = $this->createConfiguredMock(CacheInterface::class, [
            'load' => ['1.2.3.4', '20.20.20.20']
        ]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('unserialize')->willReturnArgument(0);
        $ipAddressUtil = $this->createMock(\Adyen\Util\IpAddress::class);
        $adyenLogger = $this->createMock(AdyenLogger::class);
        $configHelper = $this->createMock(Config::class);
        $configHelper->method('getNotificationsIpCheck')->willReturn(true);

        $this->ipAddressHelper = new IpAddress(
            $ipAddressUtil,
            $cache,
            $serializer,
            $adyenLogger,
            $configHelper
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
