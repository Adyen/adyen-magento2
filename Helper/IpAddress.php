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

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @var AdyenLogger $adyenLogger
     */
    protected AdyenLogger $adyenLogger;

    /** @const */
    protected static array $HOSTNAMES = array(
        'out.adyen.com',
        'outgoing1.adyen.com',
        'outgoing2.adyen.com'
    );

    /**
     * IpAddress constructor.
     *
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     */
    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        AdyenLogger $adyenLogger,
        Config $configHelper
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
    }

    /**
     * Checks if the provided array of IPs addresses has been validated
     *
     * @param string[] $ipAddresses
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isIpAddressValid(array $ipAddresses): bool
    {
        $isNotificationsIpCheckEnabled = $this->configHelper->getNotificationsIpCheck();

        if (!$isNotificationsIpCheckEnabled) {
            return true;
        }

        if (empty($ipAddresses)) {
            return false;
        }

        $cachedIpsArray = $this->getIpAddressesFromCache();

        if (empty($cachedIpsArray)) {
            $this->adyenLogger->addAdyenDebug(
                'There are no verified Adyen IP addresses in cache. Updating IP records.'
            );
           $this->updateCachedIpAddresses();
           $cachedIpsArray = $this->getIpAddressesFromCache();
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
    public function updateCachedIpAddresses(): void
    {
        $this->saveIpAddressesToCache($this->getAdyenIpAddresses());
    }

    /**
     * Saves array of IP addresses in cache key
     *
     * @param string[] $ipAddresses
     */
    public function saveIpAddressesToCache($ipAddresses): void
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
     * @return array
     */
    public function getIpAddressesFromCache(): array
    {
        $serializedIpAddresses = $this->cache->load(self::IP_ADDRESS_CACHE_ID);
        if (!empty($serializedIpAddresses)) {
            return $this->serializer->unserialize($serializedIpAddresses);
        }
        return [];
    }

    /**
     * Gets IP addresses for the Adyen webhook hostnames
     *
     * @return string[]
     */
    private function getAdyenIpAddresses(): array
    {
        $ipAddresses = array();
        foreach (self::$HOSTNAMES as $hostname) {
            $ipAddressesOfHostName = gethostbynamel($hostname);

            // gethostbynamel can return false if hostname could not be resolved
            if (false !== $ipAddressesOfHostName) {
                $ipAddresses = array_merge($ipAddresses, $ipAddressesOfHostName);
            }
        }
        return $ipAddresses;
    }
}
