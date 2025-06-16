<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Block\Form;

use Adyen\Payment\Block\Form\PayByLink;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;

class PayByLinkTest extends AbstractAdyenTestCase
{
    private PayByLink $payByLink;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(Config::class);
        $scopeConfig->method('getValue')->willReturn(AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createMock(StoreManager::class);
        $storeManager->method('getStore')->willReturn($store);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);
        $context->method('getStoreManager')->willReturn($storeManager);

        $this->payByLink = new PayByLink($context);
    }

    public function testGetDefaultExpiryDate()
    {
        $tomorrow = new \DateTime('tomorrow', new \DateTimeZone('UTC'));
        $this->assertEquals(
            $this->payByLink->getDefaultExpiryDate(),
            $tomorrow->format(AdyenPayByLinkConfigProvider::DATE_FORMAT)
        );
    }

    public function testGetMinExpiryTimestamp()
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $date->add(new \DateInterval('P' . AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS . 'D'));
        $this->assertEquals(
            $this->payByLink->getMinExpiryTimestamp(),
            $date->getTimestamp() * 1000
        );
    }

    public function testGetMaxExpiryTimestamp()
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $date->add(new \DateInterval('P' . AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS . 'D'));
        $this->assertEquals(
            $this->payByLink->getMaxExpiryTimestamp(),
            $date->getTimestamp() * 1000
        );
    }
}
