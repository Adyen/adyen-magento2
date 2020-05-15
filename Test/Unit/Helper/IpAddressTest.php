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
        $serializer = $this->getSimpleMock(\Magento\Framework\Serialize\SerializerInterface::class);
        $ipAddressUtil = $this->getSimpleMock(\Adyen\Util\IpAddress::class);

        $this->ipAddressHelper = new \Adyen\Payment\Helper\IpAddress(
            $ipAddressUtil,
            $cache,
            $serializer
        );
    }

    public function testIsIpAddressValid()
    {
        $this->assertIsBool($this->ipAddressHelper->isIpAddressValid([gethostbyname('outgoing1.adyen.com')]));
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
        $this->assertIsArray($this->ipAddressHelper->getIpAddressesFromCache());
    }

}
