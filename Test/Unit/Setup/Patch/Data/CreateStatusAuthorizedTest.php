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
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

class CreateStatusAuthorizedTest extends AbstractAdyenTestCase
{
    public function testApply()
    {
        $createStatusAuthorized = $this->getCreateStatusAuthorized();
        $createStatusAuthorized->apply();

        $this->assertTrue(true);
    }

    public function testGetAliases()
    {
        $createStatusAuthorized = $this->getCreateStatusAuthorized();
        $aliases = $createStatusAuthorized->getAliases();

        $this->assertSame([], $aliases);
    }

    public function testGetDependencies()
    {
        $createStatusAuthorized = $this->getCreateStatusAuthorized();
        $dependencies = $createStatusAuthorized::getDependencies();

        $this->assertSame([], $dependencies);
    }

    public function getVersion()
    {
        $createStatusAuthorized = $this->getCreateStatusAuthorized();
        $version = $createStatusAuthorized::getVersion();

        $this->assertNotEmpty($version);
    }

    /**
     * @return CreateStatusAuthorized
     */
    public function getCreateStatusAuthorized(): CreateStatusAuthorized
    {
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $connectionMock = $this->createMock(AdapterInterface::class);
        $connectionMock->method('select')->willReturn($selectMock);
        $connectionMock->method('fetchRow')->willReturn([]);

        $moduleDataSetupMock = $this->createConfiguredMock(ModuleDataSetupInterface::class, [
            'getConnection' => $connectionMock
        ]);
        $configWriteMock = $this->createMock(WriterInterface::class);
        $reinitableConfigMock = $this->createMock(ReinitableConfigInterface::class);
        $dataPatchHelperMock = $this->createConfiguredMock(DataPatch::class, [
            'findConfig' => null
        ]);
        $statusFactoryMock = $this->createGeneratedMock(StatusFactory::class, ['create']);
        $statusFactoryMock->method('create')->willReturn($this->createMock(Status::class));
        $statusResourceFactoryMock = $this->createGeneratedMock(StatusResourceFactory::class, ['create']);
        $statusResourceFactoryMock->method('create')
            ->willReturn($this->createMock(StatusResource::class));

        return new CreateStatusAuthorized(
            $moduleDataSetupMock,
            $configWriteMock,
            $reinitableConfigMock,
            $dataPatchHelperMock,
            $statusFactoryMock,
            $statusResourceFactoryMock
        );
    }
}
