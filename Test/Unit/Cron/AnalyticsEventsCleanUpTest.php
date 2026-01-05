<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Cron;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Cron\AnalyticsEventsCleanUp;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\Collection;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\CollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;

class AnalyticsEventsCleanUpTest extends AbstractAdyenTestCase
{
    protected ?AnalyticsEventsCleanUp $analyticsEventsCleanUp;
    protected CollectionFactory|MockObject $collectionFactoryMock;
    protected AnalyticsEvent|MockObject $analyticsEventResourceModelMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected Collection|MockObject $collectionMock;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->analyticsEventResourceModelMock = $this->createMock(AnalyticsEvent::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->collectionMock = $this->createMock(Collection::class);

        $this->analyticsEventsCleanUp = new AnalyticsEventsCleanUp(
            $this->collectionFactoryMock,
            $this->analyticsEventResourceModelMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->analyticsEventsCleanUp = null;
    }

    public function testExecuteWithEventsToCleanUp()
    {
        $entityIds = [1, 2, 3];

        $this->collectionMock->expects($this->once())
            ->method('analyticsEventsToCleanUp')
            ->willReturn($this->collectionMock);

        $this->collectionMock->expects($this->once())
            ->method('getSize')
            ->willReturn(3);

        $this->collectionMock->expects($this->once())
            ->method('getColumnValues')
            ->with(AnalyticsEventInterface::ENTITY_ID)
            ->willReturn($entityIds);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->collectionMock);

        $this->analyticsEventResourceModelMock->expects($this->once())
            ->method('deleteByIds')
            ->with($entityIds);

        $this->adyenLoggerMock->expects($this->never())
            ->method('error');

        $this->analyticsEventsCleanUp->execute();
    }

    public function testExecuteWithNoEventsToCleanUp()
    {
        $this->collectionMock->expects($this->once())
            ->method('analyticsEventsToCleanUp')
            ->willReturn($this->collectionMock);

        $this->collectionMock->expects($this->once())
            ->method('getSize')
            ->willReturn(0);

        $this->collectionMock->expects($this->never())
            ->method('getColumnValues');

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->collectionMock);

        $this->analyticsEventResourceModelMock->expects($this->never())
            ->method('deleteByIds');

        $this->adyenLoggerMock->expects($this->never())
            ->method('error');

        $this->analyticsEventsCleanUp->execute();
    }

    public function testExecuteLogsErrorOnException()
    {
        $exceptionMessage = 'Database connection error';

        $this->collectionMock->expects($this->once())
            ->method('analyticsEventsToCleanUp')
            ->willReturn($this->collectionMock);

        $this->collectionMock->expects($this->once())
            ->method('getSize')
            ->willReturn(3);

        $this->collectionMock->expects($this->once())
            ->method('getColumnValues')
            ->with(AnalyticsEventInterface::ENTITY_ID)
            ->willReturn([1, 2, 3]);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->collectionMock);

        $this->analyticsEventResourceModelMock->expects($this->once())
            ->method('deleteByIds')
            ->willThrowException(new Exception($exceptionMessage));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with(sprintf("An error occurred while cleaning up the analytics events: %s", $exceptionMessage));

        $this->analyticsEventsCleanUp->execute();
    }

    public function testExecuteLogsErrorOnCollectionException()
    {
        $exceptionMessage = 'Collection creation failed';

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willThrowException(new Exception($exceptionMessage));

        $this->analyticsEventResourceModelMock->expects($this->never())
            ->method('deleteByIds');

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with(sprintf("An error occurred while cleaning up the analytics events: %s", $exceptionMessage));

        $this->analyticsEventsCleanUp->execute();
    }
}
