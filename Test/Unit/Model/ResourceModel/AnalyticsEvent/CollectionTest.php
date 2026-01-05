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
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
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
}
