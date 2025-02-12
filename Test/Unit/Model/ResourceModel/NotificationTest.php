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

use Adyen\Payment\Model\ResourceModel\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\MockObject\MockObject;

class NotificationTest extends AbstractAdyenTestCase
{
    protected ?Notification $notificationResourceModel;
    protected Context|MockObject $contextMock;
    protected AdapterInterface|MockObject $connectionMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $resourceMock = $this->createMock(ResourceConnection::class);
        $resourceMock->method('getConnection')->willReturn($this->connectionMock);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getResources')->willReturn($resourceMock);

        $this->notificationResourceModel = new Notification($this->contextMock);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->notificationResourceModel = null;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testDeleteByIds()
    {
        $entityIds = ['1', '2', '3'];
        $mockQuery = 'DELETE FROM adyen_nofication WHERE entity_id IN (1, 2, 3)';

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->expects($this->once())
            ->method('where')
            ->with('notification.entity_id IN (?)', $entityIds)
            ->willReturnSelf();

        // Assert query builder
        $select->expects($this->once())
            ->method('deleteFromSelect')
            ->with('notification')
            ->willReturn($mockQuery);

        $this->connectionMock->expects($this->once())->method('select')->willReturn($select);
        $this->connectionMock->expects($this->once())->method('query')->with($mockQuery);

        $this->notificationResourceModel->deleteByIds($entityIds);
    }

    public function testEmptyInput()
    {
        $entityIds = [];
        $this->connectionMock->expects($this->never())->method('query');

        $this->notificationResourceModel->deleteByIds($entityIds);
    }
}
