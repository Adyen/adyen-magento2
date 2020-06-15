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
use Adyen\Payment\Logger\AdyenLogger;
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
     * @var AdyenLogger $adyenLogger
     */
    protected $adyenLogger;

    /**
     * IpAddress constructor.
     *
     * @param IpAddressUtil $ipAddressUtil
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        IpAddressUtil $ipAddressUtil,
        CacheInterface $cache,
        SerializerInterface $serializer,
        AdyenLogger $adyenLogger
    ) {
        $this->ipAddressUtil = $ipAddressUtil;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * Checks if the provided array of IPs addresses has been validated
     *
     * @param string[] $ipAddresses
     * @return bool
     */
    public function isIpAddressValid($ipAddresses)
    {
        if (empty($ipAddresses)) {
            return false;
        }

        $cachedIpsArray = $this->getIpAddressesFromCache();

        if (empty($cachedIpsArray)) {
            $this->adyenLogger->addAdyenDebug(
                'There are no verified Adyen IP addresses in cache. Updating IP records.'
            );
            $this->updateCachedIpAddresses();
        }

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
