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
use Magento\Framework\Setup\ModuleDataSetupInterface;

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

        $this->assertTrue($aliases === []);
    }

    public function testGetDependencies()
    {
        $createStatusAuthorized = $this->getCreateStatusAuthorized();
        $dependencies = $createStatusAuthorized::getDependencies();

        $this->assertTrue($dependencies === []);
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
        $moduleDataSetupMock = $this->createConfiguredMock(ModuleDataSetupInterface::class, [
            'getConnection' => $this->createMock(AdapterInterface::class)
        ]);
        $configWriteMock = $this->createMock(WriterInterface::class);
        $reinitableConfigMock = $this->createMock(ReinitableConfigInterface::class);
        $dataPatchHelperMock = $this->createConfiguredMock(DataPatch::class, [
            'findConfig' => null
        ]);

        return new CreateStatusAuthorized(
            $moduleDataSetupMock,
            $configWriteMock,
            $reinitableConfigMock,
            $dataPatchHelperMock
        );
    }
}
