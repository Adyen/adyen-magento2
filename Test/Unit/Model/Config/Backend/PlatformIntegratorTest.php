<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model\Config\Backend;

use Adyen\Payment\Model\Config\Backend\PlatformIntegrator;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class PlatformIntegratorTest extends AbstractAdyenTestCase
{
    protected ?PlatformIntegrator $platformIntegrator;
    private MockObject|Context $contextMock;
    private MockObject|Registry $registryMock;
    private MockObject|ScopeConfigInterface $scopeConfigMock;
    private MockObject|TypeListInterface $typeListMock;
    private MockObject|WriterInterface $configWriterMock;
    private array $dataMock;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->registryMock = $this->createMock(Registry::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->typeListMock = $this->createMock(TypeListInterface::class);
        $this->configWriterMock = $this->createMock(WriterInterface::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->platformIntegrator = null;
    }

    /**
     * The SUT should be generated after mocking `data` property.
     *
     * @return void
     */
    private function initiateSut(): void
    {
        $this->platformIntegrator = new PlatformIntegrator(
            $this->contextMock,
            $this->registryMock,
            $this->scopeConfigMock,
            $this->typeListMock,
            $this->configWriterMock,
            null,
            null,
            $this->dataMock
        );
    }

    /**
     * Config writer should be called to reset platform integrator name if the value of the field
     * is changed and this field is disabled.
     *
     * @return void
     */
    public function testBeforeSave()
    {
        $hasPlatformIntegratorOldValue = 1;

        $this->dataMock = ['value' => '0'];
        $this->initiateSut();

        $this->scopeConfigMock->method('getValue')->willReturn($hasPlatformIntegratorOldValue);

        // Assert calling config writer to reset the target value
        $this->configWriterMock->expects($this->once())
            ->method('save')
            ->with('payment/adyen_abstract/platform_integrator', '');

        $this->platformIntegrator->beforeSave();
    }
}
