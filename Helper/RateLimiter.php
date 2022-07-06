<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\Cache\Type\AdyenCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class RateLimiter
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
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * Initial cache lifetime
     */
    const INITIAL_COOLDOWN_PERIOD = 300;

    /**
     * Power base value
     */
    const POWER = 2;


    /**
     * RateLimiter constructor.
     *
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param RemoteAddress $remoteAddress
     */

    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        RemoteAddress $remoteAddress
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->remoteAddress = $remoteAddress;
    }


    private function getCacheId()
    {
        return "adyen-logins-" . $this->remoteAddress->getRemoteAddress();
    }

    public function saveSessionIdIpAddressToCache()
    {
        $cacheValue = $this->getNumberOfAttempts();

        $this->cache->save(
            $this->serializer->serialize($cacheValue + 1),
            $this->getCacheId(),
            [AdyenCache::CACHE_TAG],
            $this->calculateNotificationCacheLifetime($cacheValue + 1)
        );
    }

    public function getNumberOfAttempts()
    {
        $numberOfAttempts = $this->cache->load($this->getCacheId());
        return (int)$numberOfAttempts;
    }

    private function calculateNotificationCacheLifetime($numberOfAttempts)
    {

        return max(self::INITIAL_COOLDOWN_PERIOD, pow(self::POWER, $numberOfAttempts));
    }
}
