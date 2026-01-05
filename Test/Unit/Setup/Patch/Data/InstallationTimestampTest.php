<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Setup\Patch\Data;

use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Api\Data\ConfigurationEventType;
use Adyen\Payment\Helper\AnalyticsEventState;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\DataPatch;
use Adyen\Payment\Setup\Patch\Data\InstallationTimestamp;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\MockObject\MockObject;

class InstallationTimestampTest extends AbstractAdyenTestCase
{
    private InstallationTimestamp $installationTimestamp;
    private MockObject $configWriterMock;
    private MockObject $moduleDataSetupMock;
    private MockObject $dataPatchHelperMock;
    private MockObject $eventManagerMock;
    private MockObject $connectionMock;

    protected function setUp(): void
    {
        $this->configWriterMock = $this->createMock(WriterInterface::class);
        $this->moduleDataSetupMock = $this->createMock(ModuleDataSetupInterface::class);
        $this->dataPatchHelperMock = $this->createMock(DataPatch::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $this->moduleDataSetupMock->method('getConnection')
            ->willReturn($this->connectionMock);

        $this->installationTimestamp = new InstallationTimestamp(
            $this->configWriterMock,
            $this->moduleDataSetupMock,
            $this->dataPatchHelperMock,
            $this->eventManagerMock
        );
    }

    public function testApplyWhenTimestampDoesNotExist(): void
    {
        $expectedPath = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ANALYTICS_PREFIX,
            Config::XML_INSTALLATION_TIME
        );

        $this->connectionMock->expects($this->once())
            ->method('startSetup');

        $this->connectionMock->expects($this->once())
            ->method('endSetup');

        $this->dataPatchHelperMock->expects($this->once())
            ->method('findConfig')
            ->with($this->moduleDataSetupMock, $expectedPath, null)
            ->willReturn(null);

        $this->configWriterMock->expects($this->once())
            ->method('save');

        $this->eventManagerMock->expects($this->once())
            ->method('dispatch')
            ->with(
                AnalyticsEventState::EVENT_NAME,
                [
                    'data' => [
                        'type' => AnalyticsEventTypeEnum::EXPECTED_START->value,
                        'topic' => CheckoutAnalytics::TOPIC_PLUGIN_CONFIGURATION_TIME,
                        'relationId' => ConfigurationEventType::PLUGIN_INSTALLATION->value
                    ]
                ]
            );

        $result = $this->installationTimestamp->apply();

        $this->assertSame($this->installationTimestamp, $result);
    }

    public function testApplyWhenTimestampAlreadyExists(): void
    {
        $expectedPath = sprintf(
            "%s/%s/%s",
            Config::XML_PAYMENT_PREFIX,
            Config::XML_ADYEN_ANALYTICS_PREFIX,
            Config::XML_INSTALLATION_TIME
        );

        $existingConfig = [
            'config_id' => 1,
            'scope' => 'default',
            'scope_id' => 0,
            'path' => $expectedPath,
            'value' => '2024-01-01T00:00:00+00:00'
        ];

        $this->connectionMock->expects($this->once())
            ->method('startSetup');

        $this->connectionMock->expects($this->once())
            ->method('endSetup');

        $this->dataPatchHelperMock->expects($this->once())
            ->method('findConfig')
            ->with($this->moduleDataSetupMock, $expectedPath, null)
            ->willReturn($existingConfig);

        $this->configWriterMock->expects($this->never())
            ->method('save');

        $this->eventManagerMock->expects($this->never())
            ->method('dispatch');

        $result = $this->installationTimestamp->apply();

        $this->assertSame($this->installationTimestamp, $result);
    }

    public function testGetDependenciesReturnsEmptyArray(): void
    {
        $this->assertEquals([], InstallationTimestamp::getDependencies());
    }

    public function testGetAliasesReturnsEmptyArray(): void
    {
        $this->assertEquals([], $this->installationTimestamp->getAliases());
    }
}
