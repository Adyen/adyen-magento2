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

namespace Adyen\Payment\Helper;

use Adyen\Util\IpAddress as IpAddressUtil;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class IpAddress
 * @package Adyen\Payment\Helper
 */
class IpAddress
{

    const IP_ADDRESS_CACHE_ID = "Adyen_ip_address";
    const IP_ADDRESS_CACHE_LIFETIME = 86400;

    /**
     * @var IpAddressUtil
     */
    private $ipAddressUtil;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * IpAddress constructor.
     *
     * @param IpAddressUtil $ipAddressUtil
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(
        IpAddressUtil $ipAddressUtil,
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        $this->ipAddressUtil = $ipAddressUtil;
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * Checks if the provided array of IPs addresses has been validated before and updates the cache accordingly
     *
     * @param string[] $ipAddresses
     * @param bool $fullCacheKeyUpdate
     * @return bool
     */
    public function isIpAddressValid($ipAddresses)
    {
        if (empty($ipAddresses)) {
            return false;
        }

        $cachedIpsArray = $this->getIpAddressesFromCache();

        foreach ($ipAddresses as $ipAddress) {
            //If the IP is already cached return true
            if (in_array($ipAddress, $cachedIpsArray)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Updates cache key containing Adyen webhook IP addresses with newly resolved records
     */
    public function updateCachedIpAddresses()
    {
        $this->saveIpAddressesToCache($this->ipAddressUtil->getAdyenIpAddresses());
    }

    /**
     * Saves array of IP addresses in cache key
     *
     * @param string[] $ipAddresses
     */
    public function saveIpAddressesToCache($ipAddresses)
    {
        $this->cache->save(
            $this->serializer->serialize($ipAddresses),
            self::IP_ADDRESS_CACHE_ID,
            [],
            self::IP_ADDRESS_CACHE_LIFETIME
        );
    }

    /**
     * Loads value of IP addresses cache key and returns it as array
     *
     * @return array|bool|float|int|string|null
     */
    public function getIpAddressesFromCache()
    {
        $serializedIpAddresses = $this->cache->load(self::IP_ADDRESS_CACHE_ID);
        if (!empty($serializedIpAddresses)) {
            return $this->serializer->unserialize($serializedIpAddresses);
        }
        return [];
    }
}