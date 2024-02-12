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
}
