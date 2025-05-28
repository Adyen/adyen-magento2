<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com/>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Helper\Config as ConfigHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Locale
{
    private static $localeMappings = [
        'zh_Hans_CN' => 'zh-CN',
        'zh_Hant_HK' => 'zh-CN',
        'zh_Hant_TW' => 'zh-TW',
    ];

    private StoreManagerInterface $storeManager;
    private ResolverInterface $localeResolver;
    private ScopeConfigInterface $scopeConfig;
    private ConfigHelper $configHelper;

    public function __construct(
        StoreManagerInterface $storeManager,
        ResolverInterface $localeResolver,
        ScopeConfigInterface $scopeConfig,
        ConfigHelper $configHelper,
    ) {
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->scopeConfig = $scopeConfig;
        $this->configHelper = $configHelper;
    }

    /**
     * Maps Magento locale code to Adyen-compatible format.
     */
    public function mapLocaleCode(string $localeCode): string
    {
        return self::$localeMappings[$localeCode] ?? $localeCode;
    }

    /**
     * Returns store locale in Adyen-compatible format.
     */
    public function getStoreLocale(?int $storeId = null): string
    {
        $path = \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE;
        $storeLocale = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $this->mapLocaleCode($storeLocale);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed|string
     * @throws NoSuchEntityException
     */
    public function getCurrentLocaleCode($storeId)
    {
        $localeCode = $this->configHelper->getAdyenHppConfigData('shopper_locale', $storeId);
        if ($localeCode != "") {
            return $this->mapLocaleCode($localeCode);
        }

        $locale = $this->localeResolver->getLocale();
        if ($locale) {
            return $this->mapLocaleCode($locale);
        }

        // should have the value if not fall back to default
        $localeCode = $this->scopeConfig->getValue(
            \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE,
            ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore($storeId)->getCode()
        );

        return $this->mapLocaleCode($localeCode);
    }

}
