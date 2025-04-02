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
}
