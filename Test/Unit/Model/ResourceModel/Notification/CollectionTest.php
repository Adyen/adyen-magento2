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

namespace Adyen\Payment\Test\Helper\Unit\Model\ResourceModel\Notification;

use Adyen\Payment\Model\ResourceModel\Notification\Collection;
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
    protected ?Collection $notificationCollection;
    protected EntityFactoryInterface|MockObject $entityFactoryMock;
    protected LoggerInterface|MockObject $loggerMock;
    protected FetchStrategyInterface|MockObject $fetchStrategyMock;
    protected ManagerInterface|MockObject $eventManagerMock;
    protected AdapterInterface $connectionMock;
    protected AbstractDb $resourceMock;

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

        $this->notificationCollection = new Collection(
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
        $this->notificationCollection = null;
    }

    /**
     * @return void
     */
    public function testGetProcessedWebhookIdsByTimeLimit()
    {
        $processedWebhookRemovalTime = 90;
        $batchSize = 1000;

        $result = $this->notificationCollection->getProcessedWebhookIdsByTimeLimit(
            $processedWebhookRemovalTime,
            $batchSize
        );

        $this->assertInstanceOf(Collection::class, $result);
    }
}
