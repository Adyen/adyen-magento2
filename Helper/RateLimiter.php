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
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Action\Context;

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
     * @var Context
     */
    private $context;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * RateLimiter constructor.
     *
     * @param Context $context
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param Config $configHelper
     * @param RemoteAddress $remoteAddress
     */

    public function __construct(
        Context $context,
        CacheInterface $cache,
        SerializerInterface $serializer,
        Config $configHelper,
        RemoteAddress $remoteAddress
    ) {
        $this->context = $context;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->configHelper = $configHelper;
        $this->remoteAddress = $remoteAddress;
    }


    private function getCacheId()
    {
        return "adyen-logins-" . $this->configHelper->getNotificationsUsername() . "-" . $this->remoteAddress->getRemoteAddress();
    }

    public function saveNotificationUsernameToCache()
    {
        $cacheValue = $this->getNumberOfAttempts();
        // increase the value in cache
        $this->cache->save(
            $this->serializer->serialize($cacheValue + 1), // refine after our first tests
            $this->getCacheId(),
            [],
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
        return min(300, pow(2, $numberOfAttempts));
    }

}

// create new cache for specifically adyen => https://devdocs.magento.com/guides/v2.4/extension-dev-guide/cache/partial-caching/create-cache-type.html