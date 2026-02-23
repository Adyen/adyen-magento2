<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Setup\Patch\Data;

use Adyen\Payment\Helper\DataPatch;
use Adyen\Payment\Setup\Patch\Data\CreateStatusAuthorized;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\MockObject\MockObject;

class CreateStatusAuthorizedTest extends AbstractAdyenTestCase
{
    protected CreateStatusAuthorized $createStatusAuthorized;
    protected ModuleDataSetupInterface|MockObject $moduleDataSetupMock;
    protected WriterInterface|MockObject $configWriteMock;
    protected ReinitableConfigInterface|MockObject $reinitableConfigMock;
    protected DataPatch|MockObject $dataPatchHelperMock;
    protected AdapterInterface|MockObject $connectionMock;

    /**
     * @return void
     */
    protected function setUp():void
    {
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->connectionMock->method('select')->willReturn($selectMock);

        $this->moduleDataSetupMock = $this->createConfiguredMock(ModuleDataSetupInterface::class, [
            'getConnection' => $this->connectionMock,
            'getTable' => 'sales_order_status'
        ]);
        $this->configWriteMock = $this->createMock(WriterInterface::class);
        $this->reinitableConfigMock = $this->createMock(ReinitableConfigInterface::class);
        $this->dataPatchHelperMock = $this->createConfiguredMock(DataPatch::class, [
            'findConfig' => null
        ]);

        $this->createStatusAuthorized = new CreateStatusAuthorized(
            $this->moduleDataSetupMock,
            $this->configWriteMock,
            $this->reinitableConfigMock,
            $this->dataPatchHelperMock
        );
    }

    public function testApply()
    {
        $this->connectionMock->method('fetchRow')->willReturn([]);
        $this->connectionMock->expects($this->atLeastOnce())
            ->method('insert');

        $result = $this->createStatusAuthorized->apply();

        $this->assertInstanceOf(CreateStatusAuthorized::class, $result);
    }

    public function testApplyFail()
    {
        $this->connectionMock->method('fetchRow')->willReturn([
            'status' => 'adyen_authorized'
        ]);

        $this->connectionMock->expects($this->never())->method('insert');

        $result = $this->createStatusAuthorized->apply();

        $this->assertInstanceOf(CreateStatusAuthorized::class, $result);
    }

    public function testGetAliases()
    {
        $aliases = $this->createStatusAuthorized->getAliases();

        $this->assertSame([], $aliases);
    }

    public function testGetDependencies()
    {
        $dependencies = $this->createStatusAuthorized::getDependencies();

        $this->assertSame([], $dependencies);
    }

    public function getVersion()
    {
        $version = $this->createStatusAuthorized::getVersion();

        $this->assertNotEmpty($version);
    }
}
