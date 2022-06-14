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

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Adyen\Util\IpAddress as IpAddressUtil;

/**
 * Class IpAddress
 * @package Adyen\Payment\Helper
 */
class RateLimiter
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var IpAddressUtil
     */
    private $ipAddressUtil;

    /**
     * RateLimiter constructor.
     *
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param Config $configHelper
     * @param IpAddressUtil $ipAddressUtil
     */

    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        Config $configHelper,
        IpAddressUtil $ipAddressUtil
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->configHelper = $configHelper;
        $this->ipAddressUtil = $ipAddressUtil;
    }

    // update cache key containing webhook username with newly resolved records + update cache value with new expiry time

    // save array of notification usernames in cache key

    public function saveNotificationUsernamesToCache(): void
    {
        if($this->checkExistenceOfNotificationUsernameInCache()) {
            $cacheValue = $this->cache->load("adyen-logins-" . $this->configHelper->getNotificationsUsername() . $this->ipAddressUtil->getAdyenIpAddresses());

            // increase the value in cache
            $this->cache->save(
                $this->serializer->serialize($cacheValue + 1),
                "adyen-logins-" . $this->configHelper->getNotificationsUsername() . $this->ipAddressUtil->getAdyenIpAddresses(),
                [],
                $this->notificationCacheLifetime($cacheValue + 1)
            );
        } else {
            // create a new value and save it to cache
            $this->cache->save(
                $this->serializer->serialize(1),
                "adyen-logins-" . $this->configHelper->getNotificationsUsername() . $this->ipAddressUtil->getAdyenIpAddresses(),
                [],
                $this->notificationCacheLifetime($this->numberOfAttempts())
            );
        }
    }
    

    // load values of notification usernames cache key
    public function checkExistenceOfNotificationUsernameInCache(): bool
    {
        // check if there is any cache key with that cache ID
        $notificationUsername = $this->cache->load("adyen-logins-" . $this->configHelper->getNotificationsUsername() . $this->ipAddressUtil->getAdyenIpAddresses());

        if(!empty($notificationUsername)) {
            return true;
        }

        return false;
    }

    public function numberOfAttempts(): int
    {
       $count = 1;
       ++$count;
       return $count;
    } // that won't work

    private function notificationCacheLifetime($numberOfAttempts)
    {
        $initialValue = 360;
        return min($initialValue, pow(2, $numberOfAttempts) * 60);


        // test this with debugger with the correct notification pwd, make sure you are not blocking the usernames with the correct pwd

        // create new cache for specifically adyen => https://devdocs.magento.com/guides/v2.4/extension-dev-guide/cache/partial-caching/create-cache-type.html
    }

}