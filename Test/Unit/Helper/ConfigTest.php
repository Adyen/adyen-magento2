<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\Config\Source\CaptureMode;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigTest extends AbstractAdyenTestCase
{
    protected ScopeConfigInterface $scopeConfigMock;
    private EncryptorInterface $encryptorMock;
    private WriterInterface $configWriterMock;
    private SerializerInterface $serializerMock;
    private Config $configHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);
        $this->configWriterMock = $this->createMock(WriterInterface::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        $this->configHelper = new Config(
            $this->scopeConfigMock,
            $this->encryptorMock,
            $this->configWriterMock,
            $this->serializerMock
        );
    }

    public function testGetIsPaymentMethodsActive()
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->with('payment/adyen_abstract/payment_methods_active')
            ->willReturn('1');
        $this->assertTrue($this->configHelper->getIsPaymentMethodsActive());
    }

    public function testGetAllowMultistoreTokensWithEnabledSetting()
    {
        $storeId = 1;
        $expectedResult = true;
        $path = 'payment/adyen_abstract/allow_multistore_tokens';

        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo($storeId))
            ->willReturn($expectedResult);

        $result = $this->configHelper->getAllowMultistoreTokens($storeId);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetAllowMultistoreTokensWithDisabledSetting()
    {
        $storeId = 1;
        $expectedResult = false;
        $path = 'payment/adyen_abstract/allow_multistore_tokens';

        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo($storeId))
            ->willReturn($expectedResult);

        $result = $this->configHelper->getAllowMultistoreTokens($storeId);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetAdyenPosCloudPaymentAction()
    {
        $storeId = PHP_INT_MAX;

        $expectedResult = MethodInterface::ACTION_ORDER;
        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_POS_CLOUD,
            Config::XML_PAYMENT_ACTION
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo($storeId))
            ->willReturn($expectedResult);

        $result = $this->configHelper->getAdyenPosCloudPaymentAction($storeId);

        $this->assertEquals($expectedResult, $result);
    }

    public function testSetConfigData()
    {
        $value = 'TEST_VALUE';
        $field = 'test_path';
        $xml_prefix = Config::XML_ADYEN_ABSTRACT_PREFIX;

        $this->configWriterMock->expects($this->once())->method('save');

        $this->configHelper->setConfigData($value, $field, $xml_prefix);
    }

    public function testRemoveConfigData()
    {
        $field = 'test_path';
        $xml_prefix = Config::XML_ADYEN_ABSTRACT_PREFIX;

        $this->configWriterMock->expects($this->once())->method('delete');

        $this->configHelper->removeConfigData($field, $xml_prefix);
    }

    public function testGetThreeDSModes()
    {
        $storeId = PHP_INT_MAX;
        $expectedResult = MethodInterface::ACTION_ORDER;
        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_CC,
            Config::XML_THREEDS_FLOW
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo($storeId))
            ->willReturn($expectedResult);

        $result = $this->configHelper->getThreeDSFlow($storeId);
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetIsCvcRequiredForRecurringCardPayments()
    {
        $storeId = PHP_INT_MAX;
        $expectedResult = true;

        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_CC_VAULT,
            'require_cvc'
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo($storeId))
            ->willReturn($expectedResult);

        $result = $this->configHelper->getIsCvcRequiredForRecurringCardPayments($storeId);
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetIsWebhookCleanupEnabled()
    {
        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            Config::XML_REMOVE_PROCESSED_WEBHOOKS
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo(null))
            ->willReturn(true);

        $result = $this->configHelper->getIsProcessedWebhookRemovalEnabled();
        $this->assertTrue($result);
    }

    public function testGetRequiredDaysForOldWebhooks()
    {
        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            Config::XML_PROCESSED_WEBHOOK_REMOVAL_TIME
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo(null))
            ->willReturn("90");

        $result = $this->configHelper->getProcessedWebhookRemovalTime();
        $this->assertIsInt($result);
        $this->assertEquals(90, $result);
    }

    public function testGetHAsPlatformIntegrator()
    {
        $hasPlatformIntegrator = true;

        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            Config::XML_HAS_PLATFORM_INTEGRATOR
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with($path, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($hasPlatformIntegrator);

        $this->assertTrue($this->configHelper->getHasPlatformIntegrator());
    }

    public function testGetPlatformIntegratorName()
    {
        $mockIntegratorName = 'Galaxy Invaders Tech Co.';

        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            Config::XML_PLATFORM_INTEGRATOR
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with($path, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($mockIntegratorName);

        $this->assertEquals($mockIntegratorName, $this->configHelper->getPlatformIntegratorName());
    }

    public function testIsOutsideCheckoutDataCollectionEnabled()
    {
        $storeId = PHP_INT_MAX;
        $expectedResult = true;

        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            Config::XML_OUTSIDE_CHECKOUT_DATA_COLLECTION
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo($storeId))
            ->willReturn($expectedResult);

        $result = $this->configHelper->isOutsideCheckoutDataCollectionEnabled($storeId);
        $this->assertEquals($expectedResult, $result);
    }

    public function testIsExpireWebhookIgnored()
    {
        $storeId = PHP_INT_MAX;
        $expectedResult = true;

        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            Config::XML_IGNORE_EXPIRE_WEBHOOK
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with($this->equalTo($path), $this->equalTo(ScopeInterface::SCOPE_STORE), $this->equalTo($storeId))
            ->willReturn($expectedResult);

        $result = $this->configHelper->isExpireWebhookIgnored($storeId);
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetCaptureMode()
    {
        $storeId = PHP_INT_MAX;
        $captureMode = CaptureMode::CAPTURE_MODE_AUTO;

        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            Config::XML_CAPTURE_MODE
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with($path, ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn($captureMode);

        $this->assertEquals($captureMode, $this->configHelper->getCaptureMode($storeId));
    }

    /**
     * @return void
     */
    public function testGetPlatformsStore()
    {
        $storeId = PHP_INT_MAX;
        $platformsStore = 'MOCK_AFP_STORE';

        $path = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_FOR_PLATFORMS_PREFIX,
            Config::XML_ADYEN_FOR_PLATFORMS_STORE
        );

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with($path, ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn($platformsStore);

        $this->assertEquals($platformsStore, $this->configHelper->getPlatformsStore($storeId));
    }
}
