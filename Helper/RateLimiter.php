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
use Adyen\Payment\Logger\AdyenLogger;

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
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * Initial cache lifetime
     */
    const INITIAL_COOLDOWN_PERIOD = 300;

    /**
     * Power base value
     */
    const POWER = 2;

    /**
     * Number of allowed notification requests
     */
    const NUMBER_OF_ATTEMPTS = 6;


    /**
     * RateLimiter constructor.
     *
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param RemoteAddress $remoteAddress
     * @param AdyenLogger $adyenLogger
     */

    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        RemoteAddress $remoteAddress,
        AdyenLogger $adyenLogger
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->remoteAddress = $remoteAddress;
        $this->adyenLogger = $adyenLogger;
    }


    private function getCacheId()
    {
        return "adyen-logins-" . $this->remoteAddress->getRemoteAddress();
    }

    public function saveSessionIdIpAddressToCache()
    {
        $cacheValue = $this->getNumberOfAttempts();

        if($cacheValue === self::NUMBER_OF_ATTEMPTS) {
            $this->adyenLogger->addAdyenDebug(
                sprintf("Webhook from IP Address %s has been rejected because the allowed number of authentication attempts has been exceeded.", $this->remoteAddress->getRemoteAddress())
            );
        }

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
