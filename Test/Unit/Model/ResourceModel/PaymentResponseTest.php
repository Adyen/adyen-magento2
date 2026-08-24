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

namespace Adyen\Payment\Test\Unit\Model\ResourceModel;

use Adyen\Payment\Model\ResourceModel\PaymentResponse;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentResponseTest extends AbstractAdyenTestCase
{
    protected ?PaymentResponse $paymentResponseResourceModel;
    protected Context|MockObject $contextMock;
    protected AdapterInterface|MockObject $connectionMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(AdapterInterface::class);

        $resourceMock = $this->createMock(ResourceConnection::class);
        $resourceMock->method('getConnection')->willReturn($this->connectionMock);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getResources')->willReturn($resourceMock);

        $this->paymentResponseResourceModel = new PaymentResponse($this->contextMock);
    }

    protected function tearDown(): void
    {
        $this->paymentResponseResourceModel = null;
    }

    public function testDeleteByIds()
    {
        $entityIds = [1, 2, 3];
        $mockQuery = 'DELETE FROM adyen_payment_response WHERE entity_id IN (1, 2, 3)';

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->expects($this->once())
            ->method('where')
            ->with('payment_response.entity_id IN (?)', $entityIds)
            ->willReturnSelf();

        $select->expects($this->once())
            ->method('deleteFromSelect')
            ->with('payment_response')
            ->willReturn($mockQuery);

        $this->connectionMock->expects($this->once())->method('select')->willReturn($select);
        $this->connectionMock->expects($this->once())->method('query')->with($mockQuery);

        $this->paymentResponseResourceModel->deleteByIds($entityIds);
    }

    public function testDeleteByIdsWithEmptyInput()
    {
        $this->connectionMock->expects($this->never())->method('select');
        $this->connectionMock->expects($this->never())->method('query');

        $this->paymentResponseResourceModel->deleteByIds([]);
    }
}
