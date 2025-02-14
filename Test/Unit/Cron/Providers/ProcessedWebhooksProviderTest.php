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

namespace Adyen\Payment\Test\Cron\Providers;

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Cron\Providers\ProcessedWebhooksProvider;
use Adyen\Payment\Cron\Providers\WebhooksProviderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\ResourceModel\Notification\Collection;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProcessedWebhooksProviderTest extends AbstractAdyenTestCase
{
    protected ?ProcessedWebhooksProvider $notificationsProvider;
    protected Config|MockObject $configHelperMock;
    protected CollectionFactory|MockObject $collectionFactoryMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->collectionFactoryMock = $this->createGeneratedMock(CollectionFactory::class, [
            'create'
        ]);

        $this->notificationsProvider = new ProcessedWebhooksProvider(
            $this->collectionFactoryMock,
            $this->configHelperMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->notificationsProvider = null;
    }

    /**
     * @return void
     */
    public function testProvideSuccess()
    {
        $expiryDays = 90;

        $this->configHelperMock->expects($this->once())
            ->method('getProcessedWebhookRemovalTime')
            ->willReturn($expiryDays);

        $collectionMock = $this->createMock(Collection::class);
        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $collectionMock->expects($this->once())->method('getProcessedWebhookIdsByTimeLimit')
            ->with($expiryDays, WebhooksProviderInterface::BATCH_SIZE);

        $collectionValue = ['1', '2', '3'];

        $collectionMock->expects($this->once())->method('getSize')->willReturn(count($collectionValue));
        $collectionMock->expects($this->once())->method('getColumnValues')
            ->with(NotificationInterface::ENTITY_ID)
            ->willReturn($collectionValue);

        $result = $this->notificationsProvider->provide();
        $this->assertEquals($collectionValue, $result);
    }

    /**
     * @return void
     */
    public function testProvideNoValues()
    {
        $expiryDays = 90;

        $this->configHelperMock->expects($this->once())
            ->method('getProcessedWebhookRemovalTime')
            ->willReturn($expiryDays);

        $collectionMock = $this->createMock(Collection::class);
        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $collectionMock->expects($this->once())->method('getProcessedWebhookIdsByTimeLimit')
            ->with($expiryDays, WebhooksProviderInterface::BATCH_SIZE);

        $collectionMock->expects($this->once())->method('getSize')->willReturn(0);
        $collectionMock->expects($this->never())->method('getColumnValues');

        $result = $this->notificationsProvider->provide();
        $this->assertEmpty($result);
    }

    /**
     * @return void
     */
    public function testGetProviderName()
    {
        $this->assertEquals(
            'Adyen processed webhooks provider',
            $this->notificationsProvider->getProviderName()
        );
    }
}
