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
use Adyen\Payment\Helper\Config;
use Magento\Framework\Serialize\SerializerInterface;

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
     * IpAddress constructor.
     *
     * @param CacheInterface $cache
     */

    /**
     * Json constructor.
     *
     * @param Config $configHelper
     * @param SerializerInterface $serializer
     */
    public function __construct(
        CacheInterface $cache,
        Config $configHelper,
        SerializerInterface $serializer
    ) {
        $this->cache = $cache;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
    }

    // update cache key containing webhook username with newly resolved records + update cache value with new expiry time

    // save array of notification usernames in in cache key

    public function saveNotificationUsernamesToCache()
    {
        if($this->checkExistenceOfNotificationUsernameInCache()) {
            $cacheValue = $this->cache->load("adyen-logins-" . $this->configHelper->getNotificationsUsername());

            // increase the value in cache
            $this->cache->save(
                $this->serializer->serialize($cacheValue + 1),
                "adyen-logins-" . $this->configHelper->getNotificationsUsername(),
                [],
                $this->notificationCacheLifetime($cacheValue + 1)
            );
            // save new value to cache with the same cache ID
        } else {
            // create a new value and save it to cache
            $blabla = $this->cache->save(
                $this->serializer->serialize(1),
                "adyen-logins-" . $this->configHelper->getNotificationsUsername(),
                [],
                $this->notificationCacheLifetime(1)
            );
        }
    }
    

    // load values of notification usernames cache key
    public function checkExistenceOfNotificationUsernameInCache()
    {
        // check if there is any cache key with that cache ID
        $notificationUsernames = $this->cache->load("adyen-logins-" . $this->configHelper->getNotificationsUsername());

        if(!empty($notificationUsernames)) {
            return true;
        }

        return false;
    }

    private function notificationCacheLifetime($numberOfAttempts)
    {
        // min(ONE_MONTH, pow(2, $numberOfAttempts))

        // ONE_MONTH = 86400 * 30
        return 86400;
        // investigate on how to delete the custom cache (first look into the capabilities of the bin/magento c:c)
        // https://store.magenest.com/blog/how-to-clean-and-flush-cache/
        // change the names of the methods to the more appropriate ones
        // test this with debugger with the correct notification pwd, make sure you are not blocking the usernames with the correct pwd
    }

}