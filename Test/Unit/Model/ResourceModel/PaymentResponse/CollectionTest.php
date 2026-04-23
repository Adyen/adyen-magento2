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

namespace Adyen\Payment\Test\Unit\Model\ResourceModel\PaymentResponse;

use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection;
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
    protected ?Collection $collection;
    protected EntityFactoryInterface|MockObject $entityFactoryMock;
    protected LoggerInterface|MockObject $loggerMock;
    protected FetchStrategyInterface|MockObject $fetchStrategyMock;
    protected ManagerInterface|MockObject $eventManagerMock;
    protected AdapterInterface|MockObject $connectionMock;
    protected AbstractDb|MockObject $resourceMock;
    protected Select|MockObject $selectMock;

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

        $this->selectMock = $this->createMock(Select::class);
        $this->selectMock->method('from')->willReturnSelf();
        $this->selectMock->method('join')->willReturnSelf();
        $this->selectMock->method('joinLeft')->willReturnSelf();
        $this->selectMock->method('where')->willReturnSelf();
        $this->selectMock->method('order')->willReturnSelf();
        $this->selectMock->method('limit')->willReturnSelf();

        $this->connectionMock->method('select')->willReturn($this->selectMock);

        $this->resourceMock->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceMock->method('getMainTable')->willReturn('adyen_payment_response');
        $this->resourceMock->method('getTable')->willReturnCallback(
            fn (string $table) => $table
        );

        $this->collection = new Collection(
            $this->entityFactoryMock,
            $this->loggerMock,
            $this->fetchStrategyMock,
            $this->eventManagerMock,
            $this->connectionMock,
            $this->resourceMock
        );
    }

    protected function tearDown(): void
    {
        $this->collection = null;
    }

    public function testGetFinalizedPaymentResponseIdsReturnsEntityIds()
    {
        $expectedIds = ['1', '2', '3'];

        $this->selectMock->expects($this->once())
            ->method('join')
            ->with(
                $this->equalTo(['so' => 'sales_order']),
                $this->stringContains('payment_response.merchant_reference = so.increment_id'),
                $this->equalTo([])
            )
            ->willReturnSelf();

        $this->selectMock->expects($this->atLeastOnce())
            ->method('where')
            ->willReturnSelf();

        $this->selectMock->expects($this->once())
            ->method('limit')
            ->with(500)
            ->willReturnSelf();

        $this->connectionMock->expects($this->once())
            ->method('fetchCol')
            ->with($this->selectMock)
            ->willReturn($expectedIds);

        $this->assertSame($expectedIds, $this->collection->getFinalizedPaymentResponseIds(500));
    }

    public function testGetFinalizedPaymentResponseIdsReturnsEmptyArray()
    {
        $this->connectionMock->expects($this->once())
            ->method('fetchCol')
            ->willReturn([]);

        $this->assertSame([], $this->collection->getFinalizedPaymentResponseIds(1000));
    }

    public function testGetOrphanPaymentResponseIdsReturnsEntityIds()
    {
        $expectedIds = ['10', '20'];

        $this->selectMock->expects($this->once())
            ->method('joinLeft')
            ->with(
                $this->equalTo(['so' => 'sales_order']),
                $this->stringContains('payment_response.merchant_reference = so.increment_id'),
                $this->equalTo([])
            )
            ->willReturnSelf();

        $this->selectMock->expects($this->once())
            ->method('limit')
            ->with(1000)
            ->willReturnSelf();

        $this->connectionMock->expects($this->once())
            ->method('fetchCol')
            ->with($this->selectMock)
            ->willReturn($expectedIds);

        $this->assertSame($expectedIds, $this->collection->getOrphanPaymentResponseIds(1, 1000));
    }

    public function testGetOrphanPaymentResponseIdsReturnsEmptyArray()
    {
        $this->connectionMock->expects($this->once())
            ->method('fetchCol')
            ->willReturn([]);

        $this->assertSame([], $this->collection->getOrphanPaymentResponseIds(1, 1000));
    }
}
