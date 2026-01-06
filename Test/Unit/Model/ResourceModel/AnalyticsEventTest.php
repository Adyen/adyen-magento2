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

namespace Adyen\Payment\Test\Helper\Unit\Model\ResourceModel;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\MockObject\MockObject;

class AnalyticsEventTest extends AbstractAdyenTestCase
{
    private ?AnalyticsEvent $analyticsEventResourceModel;
    private Context|MockObject $contextMock;
    private AdapterInterface|MockObject $connectionMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);
        $resourceConnectionMock->method('getTableName')
            ->with(AnalyticsEventInterface::ADYEN_ANALYTICS_EVENT)
            ->willReturn(AnalyticsEventInterface::ADYEN_ANALYTICS_EVENT);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getResources')->willReturn($resourceConnectionMock);

        $this->analyticsEventResourceModel = new AnalyticsEvent($this->contextMock);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->analyticsEventResourceModel = null;
    }

    /**
     * @return void
     */
    public function testGetMainTable()
    {
        $mainTable = $this->analyticsEventResourceModel->getMainTable();

        $this->assertEquals(AnalyticsEventInterface::ADYEN_ANALYTICS_EVENT, $mainTable);
    }

    /**
     * @return void
     */
    public function testGetIdFieldName()
    {
        $idFieldName = $this->analyticsEventResourceModel->getIdFieldName();

        $this->assertEquals(AnalyticsEventInterface::ENTITY_ID, $idFieldName);
    }

    /**
     * @return void
     */
    public function testDeleteByIdsWithEmptyArray()
    {
        $this->connectionMock->expects($this->never())
            ->method('select');

        $this->connectionMock->expects($this->never())
            ->method('query');

        $this->analyticsEventResourceModel->deleteByIds([]);
    }

    /**
     * @return void
     */
    public function testDeleteByIdsWithValidIds()
    {
        $entityIds = [1, 2, 3];
        $deleteQuery = 'DELETE FROM analytics_event WHERE entity_id IN (1, 2, 3)';

        $selectMock = $this->createMock(Select::class);
        $selectMock->expects($this->once())
            ->method('from')
            ->with([AnalyticsEventInterface::TABLE_NAME_ALIAS => AnalyticsEventInterface::ADYEN_ANALYTICS_EVENT])
            ->willReturnSelf();

        $selectMock->expects($this->once())
            ->method('where')
            ->with(
                sprintf(
                    "%s.%s IN (?)",
                    AnalyticsEventInterface::TABLE_NAME_ALIAS,
                    AnalyticsEventInterface::ENTITY_ID
                ),
                $entityIds
            )
            ->willReturnSelf();

        $selectMock->expects($this->once())
            ->method('deleteFromSelect')
            ->with(AnalyticsEventInterface::TABLE_NAME_ALIAS)
            ->willReturn($deleteQuery);

        $this->connectionMock->expects($this->once())
            ->method('select')
            ->willReturn($selectMock);

        $this->connectionMock->expects($this->once())
            ->method('query')
            ->with($deleteQuery);

        $this->analyticsEventResourceModel->deleteByIds($entityIds);
    }

    /**
     * @return void
     */
    public function testDeleteByIdsWithSingleId()
    {
        $entityIds = [42];
        $deleteQuery = 'DELETE FROM analytics_event WHERE entity_id IN (42)';

        $selectMock = $this->createMock(Select::class);
        $selectMock->expects($this->once())
            ->method('from')
            ->with([AnalyticsEventInterface::TABLE_NAME_ALIAS => AnalyticsEventInterface::ADYEN_ANALYTICS_EVENT])
            ->willReturnSelf();

        $selectMock->expects($this->once())
            ->method('where')
            ->with(
                sprintf(
                    "%s.%s IN (?)",
                    AnalyticsEventInterface::TABLE_NAME_ALIAS,
                    AnalyticsEventInterface::ENTITY_ID
                ),
                $entityIds
            )
            ->willReturnSelf();

        $selectMock->expects($this->once())
            ->method('deleteFromSelect')
            ->with(AnalyticsEventInterface::TABLE_NAME_ALIAS)
            ->willReturn($deleteQuery);

        $this->connectionMock->expects($this->once())
            ->method('select')
            ->willReturn($selectMock);

        $this->connectionMock->expects($this->once())
            ->method('query')
            ->with($deleteQuery);

        $this->analyticsEventResourceModel->deleteByIds($entityIds);
    }
}
