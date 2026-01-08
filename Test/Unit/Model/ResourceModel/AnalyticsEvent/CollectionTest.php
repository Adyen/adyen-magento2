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

namespace Adyen\Payment\Test\Helper\Unit\Model\ResourceModel\AnalyticsEvent;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Api\Data\AnalyticsEventStatusEnum;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Cron\Providers\AnalyticsEventProviderInterface;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\Collection;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class CollectionTest extends AbstractAdyenTestCase
{
    protected ?Collection $analyticsEventCollection;
    protected EntityFactoryInterface|MockObject $entityFactoryMock;
    protected LoggerInterface|MockObject $loggerMock;
    protected FetchStrategyInterface|MockObject $fetchStrategyMock;
    protected ManagerInterface|MockObject $eventManagerMock;
    protected AdapterInterface|MockObject $connectionMock;
    protected AbstractDb|MockObject $resourceMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        ObjectManager::setInstance($objectManagerMock);

        $this->entityFactoryMock = $this->createMock(EntityFactoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->fetchStrategyMock = $this->createMock(FetchStrategyInterface::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->resourceMock = $this->createMock(AbstractDb::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $this->connectionMock->method('select')->willReturn($select);
        $this->resourceMock->method('getConnection')->willReturn($this->connectionMock);

        $this->analyticsEventCollection = new Collection(
            $this->entityFactoryMock,
            $this->loggerMock,
            $this->fetchStrategyMock,
            $this->eventManagerMock,
            $this->connectionMock,
            $this->resourceMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->analyticsEventCollection = null;
    }

    /**
     * @return void
     * @throws AdyenException
     */
    public function testPendingAnalyticsEventsWithValidTypes()
    {
        $analyticsEventTypes = [
            AnalyticsEventTypeEnum::EXPECTED_START,
            AnalyticsEventTypeEnum::EXPECTED_END
        ];

        $result = $this->analyticsEventCollection->pendingAnalyticsEvents($analyticsEventTypes);

        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * @return void
     * @throws AdyenException
     */
    public function testPendingAnalyticsEventsWithSingleType()
    {
        $analyticsEventTypes = [AnalyticsEventTypeEnum::UNEXPECTED_END];

        $result = $this->analyticsEventCollection->pendingAnalyticsEvents($analyticsEventTypes);

        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * @return void
     */
    public function testPendingAnalyticsEventsWithAllTypes()
    {
        $analyticsEventTypes = [
            AnalyticsEventTypeEnum::EXPECTED_START,
            AnalyticsEventTypeEnum::EXPECTED_END,
            AnalyticsEventTypeEnum::UNEXPECTED_END
        ];

        $result = $this->analyticsEventCollection->pendingAnalyticsEvents($analyticsEventTypes);

        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * @return void
     */
    public function testPendingAnalyticsEventsThrowsExceptionForEmptyArray()
    {
        $this->expectException(AdyenException::class);
        $this->expectExceptionMessage('Empty required analyticsEventTypes argument!');

        $this->analyticsEventCollection->pendingAnalyticsEvents([]);
    }

    /**
     * @return void
     */
    public function testPendingAnalyticsEventsThrowsExceptionForInvalidTypes()
    {
        $this->expectException(AdyenException::class);
        $this->expectExceptionMessage('Invalid analyticsEventTypes argument!');

        $this->analyticsEventCollection->pendingAnalyticsEvents(['invalidType', 'anotherInvalidType']);
    }

    /**
     * @return void
     */
    public function testPendingAnalyticsEventsThrowsExceptionForMixedInvalidTypes()
    {
        $this->expectException(AdyenException::class);
        $this->expectExceptionMessage('Invalid analyticsEventTypes argument!');

        $this->analyticsEventCollection->pendingAnalyticsEvents([null, 123, 'string']);
    }

    /**
     * @return void
     */
    public function testAnalyticsEventsToCleanUpReturnsCollection()
    {
        $result = $this->analyticsEventCollection->analyticsEventsToCleanUp();

        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * @return void
     */
    public function testAnalyticsEventsToCleanUpReturnsSameInstance()
    {
        $result = $this->analyticsEventCollection->analyticsEventsToCleanUp();

        $this->assertSame($this->analyticsEventCollection, $result);
    }

    /**
     * @return void
     */
    public function testAnalyticsEventsToCleanUpFiltersCorrectFields()
    {
        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        ObjectManager::setInstance($objectManagerMock);

        $entityFactoryMock = $this->createMock(EntityFactoryInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $fetchStrategyMock = $this->createMock(FetchStrategyInterface::class);
        $eventManagerMock = $this->createMock(ManagerInterface::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $resourceMock = $this->createMock(AbstractDb::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connectionMock->method('select')->willReturn($select);
        $resourceMock->method('getConnection')->willReturn($connectionMock);

        $collectionMock = $this->getMockBuilder(Collection::class)
            ->setConstructorArgs([
                $entityFactoryMock,
                $loggerMock,
                $fetchStrategyMock,
                $eventManagerMock,
                $connectionMock,
                $resourceMock
            ])
            ->onlyMethods(['addFieldToFilter', 'setPageSize'])
            ->getMock();

        $collectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with(
                [
                    AnalyticsEventInterface::STATUS,
                    AnalyticsEventInterface::CREATED_AT
                ],
                $this->callback(function ($conditions) {
                    return isset($conditions[0]['eq']) &&
                        $conditions[0]['eq'] === AnalyticsEventStatusEnum::DONE->value &&
                        isset($conditions[1]['lt']);
                })
            )
            ->willReturnSelf();

        $collectionMock->expects($this->once())
            ->method('setPageSize')
            ->with(AnalyticsEventProviderInterface::CLEAN_UP_BATCH_SIZE)
            ->willReturnSelf();

        $result = $collectionMock->analyticsEventsToCleanUp();

        $this->assertSame($collectionMock, $result);
    }

    /**
     * @return void
     */
    public function testAnalyticsEventsToCleanUpSetsCorrectPageSize()
    {
        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        ObjectManager::setInstance($objectManagerMock);

        $entityFactoryMock = $this->createMock(EntityFactoryInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $fetchStrategyMock = $this->createMock(FetchStrategyInterface::class);
        $eventManagerMock = $this->createMock(ManagerInterface::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $resourceMock = $this->createMock(AbstractDb::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connectionMock->method('select')->willReturn($select);
        $resourceMock->method('getConnection')->willReturn($connectionMock);

        $collectionMock = $this->getMockBuilder(Collection::class)
            ->setConstructorArgs([
                $entityFactoryMock,
                $loggerMock,
                $fetchStrategyMock,
                $eventManagerMock,
                $connectionMock,
                $resourceMock
            ])
            ->onlyMethods(['addFieldToFilter', 'setPageSize'])
            ->getMock();

        $collectionMock->method('addFieldToFilter')->willReturnSelf();

        $collectionMock->expects($this->once())
            ->method('setPageSize')
            ->with(AnalyticsEventProviderInterface::CLEAN_UP_BATCH_SIZE)
            ->willReturnSelf();

        $collectionMock->analyticsEventsToCleanUp();
    }

    /**
     * @return void
     */
    public function testAnalyticsEventsToCleanUpFiltersDoneStatusOrOldRecords()
    {
        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        ObjectManager::setInstance($objectManagerMock);

        $entityFactoryMock = $this->createMock(EntityFactoryInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $fetchStrategyMock = $this->createMock(FetchStrategyInterface::class);
        $eventManagerMock = $this->createMock(ManagerInterface::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $resourceMock = $this->createMock(AbstractDb::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connectionMock->method('select')->willReturn($select);
        $resourceMock->method('getConnection')->willReturn($connectionMock);

        $collectionMock = $this->getMockBuilder(Collection::class)
            ->setConstructorArgs([
                $entityFactoryMock,
                $loggerMock,
                $fetchStrategyMock,
                $eventManagerMock,
                $connectionMock,
                $resourceMock
            ])
            ->onlyMethods(['addFieldToFilter', 'setPageSize'])
            ->getMock();

        $collectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with(
                [
                    AnalyticsEventInterface::STATUS,
                    AnalyticsEventInterface::CREATED_AT
                ],
                $this->callback(function ($conditions) {
                    $daysLimit = date('Y-m-d H:i:s', strtotime('-45 days'));
                    $daysLimitTimestamp = strtotime($daysLimit);

                    $hasDoneStatus = isset($conditions[0]['eq']) &&
                        $conditions[0]['eq'] === AnalyticsEventStatusEnum::DONE->value;

                    $hasDateFilter = isset($conditions[1]['lt']);

                    if ($hasDateFilter) {
                        $filterTimestamp = strtotime($conditions[1]['lt']);
                        $timeDiff = abs($filterTimestamp - $daysLimitTimestamp);
                        $hasDateFilter = $timeDiff < 60;
                    }

                    return $hasDoneStatus && $hasDateFilter;
                })
            )
            ->willReturnSelf();

        $collectionMock->method('setPageSize')->willReturnSelf();

        $collectionMock->analyticsEventsToCleanUp();
    }
}
