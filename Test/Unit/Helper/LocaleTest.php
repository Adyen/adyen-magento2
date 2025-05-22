<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Locale;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locale::class)]
class LocaleTest extends TestCase
{
    private Locale $localeHelper;
    private MockObject $storeManager;
    private MockObject $localeResolver;
    private MockObject $scopeConfig;
    private MockObject $configHelper;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->localeResolver = $this->createMock(ResolverInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->configHelper = $this->createMock(Config::class);

        $this->localeHelper = new Locale(
            $this->storeManager,
            $this->localeResolver,
            $this->scopeConfig,
            $this->configHelper
        );
    }

    public function testMapLocaleCode(): void
    {
        $this->assertSame('zh-CN', $this->localeHelper->mapLocaleCode('zh_Hans_CN'));
        $this->assertSame('fr_FR', $this->localeHelper->mapLocaleCode('fr_FR')); // unmapped
    }

    public function testGetStoreLocale(): void
    {
        $storeId = 5;
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(\Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE, ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn('zh_Hant_TW');

        $result = $this->localeHelper->getStoreLocale($storeId);
        $this->assertSame('zh-TW', $result);
    }

    public function testGetCurrentLocaleCodeFromAdyenConfig(): void
    {
        $this->configHelper
            ->expects(self::once())
            ->method('getAdyenHppConfigData')
            ->with('shopper_locale', 1)
            ->willReturn('zh_Hans_CN');

        $this->localeResolver
            ->expects(self::never())
            ->method('getLocale');

        $this->scopeConfig
            ->expects(self::never())
            ->method('getValue');

        $this->assertSame('zh-CN', $this->localeHelper->getCurrentLocaleCode(1));
    }

    public function testGetCurrentLocaleCodeFromLocaleResolver(): void
    {
        $this->configHelper
            ->expects(self::once())
            ->method('getAdyenHppConfigData')
            ->willReturn('');

        $this->localeResolver
            ->expects(self::once())
            ->method('getLocale')
            ->willReturn('zh_Hant_HK');

        $this->scopeConfig
            ->expects(self::never())
            ->method('getValue');

        $this->assertSame('zh-CN', $this->localeHelper->getCurrentLocaleCode(2));
    }

    public function testGetCurrentLocaleCodeFromDefaultLocale(): void
    {
        $this->configHelper
            ->expects(self::once())
            ->method('getAdyenHppConfigData')
            ->willReturn('');

        $this->localeResolver
            ->expects(self::once())
            ->method('getLocale')
            ->willReturn('');

        $store = $this->createMock(Store::class);
        $store->expects(self::once())->method('getCode')->willReturn('default');

        $this->storeManager
            ->expects(self::once())
            ->method('getStore')
            ->with(3)
            ->willReturn($store);

        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(\Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE, ScopeInterface::SCOPE_STORES, 'default')
            ->willReturn('zh_Hant_TW');

        $this->assertSame('zh-TW', $this->localeHelper->getCurrentLocaleCode(3));
    }
}
