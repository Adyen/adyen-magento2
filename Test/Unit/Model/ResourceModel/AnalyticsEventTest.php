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
}
