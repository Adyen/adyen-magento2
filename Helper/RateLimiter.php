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
     * @var Config
     */
    private $configHelper;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * RateLimiter constructor.
     *
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param Config $configHelper
     * @param RemoteAddress $remoteAddress
     */

    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        Config $configHelper,
        RemoteAddress $remoteAddress
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->configHelper = $configHelper;
        $this->remoteAddress = $remoteAddress;
    }


    private function getCacheId()
    {
        return "adyen-logins-" . $this->configHelper->getNotificationsUsername() . "-" . $this->remoteAddress->getRemoteAddress();
    }

    public function saveNotificationUsernameIpAddressToCache()
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

        if($numberOfAttempts === false) {
            return 0;
        } else {
            return $numberOfAttempts;
        }
    }

    private function calculateNotificationCacheLifetime($numberOfAttempts)
    {
        return max(300, pow(2, $numberOfAttempts));
    }
}