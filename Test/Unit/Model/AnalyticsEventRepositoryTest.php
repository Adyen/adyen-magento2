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

namespace Adyen\Payment\Test\Helper\Unit\Model;

use Adyen\Payment\Model\AnalyticsEvent;
use Adyen\Payment\Model\AnalyticsEventFactory;
use Adyen\Payment\Model\AnalyticsEventRepository;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent as AnalyticsEventResourceModel;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;

class AnalyticsEventRepositoryTest extends AbstractAdyenTestCase
{
    protected ?AnalyticsEventRepository $analyticsEventRepository;
    protected AnalyticsEventResourceModel|MockObject $resourceModelMock;
    protected AnalyticsEventFactory|MockObject $analyticsEventFactoryMock;

    protected function setUp(): void
    {
        $this->resourceModelMock = $this->createMock(AnalyticsEventResourceModel::class);
        $this->analyticsEventFactoryMock = $this->createGeneratedMock(
            AnalyticsEventFactory::class,
            ['create']
        );

        $this->analyticsEventRepository = new AnalyticsEventRepository(
            $this->resourceModelMock,
            $this->analyticsEventFactoryMock
        );
    }

    protected function tearDown(): void
    {
        $this->analyticsEventRepository = null;
    }

    public function testSave(): void
    {
        $analyticsEventMock = $this->createMock(AnalyticsEvent::class);

        $this->resourceModelMock->expects($this->once())
            ->method('save')
            ->with($analyticsEventMock)
            ->willReturnSelf();

        $result = $this->analyticsEventRepository->save($analyticsEventMock);

        $this->assertSame($analyticsEventMock, $result);
    }

    public function testGetByIdSuccess(): void
    {
        $entityId = 123;
        $analyticsEventMock = $this->createMock(AnalyticsEvent::class);

        $this->analyticsEventFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($analyticsEventMock);

        $this->resourceModelMock->expects($this->once())
            ->method('load')
            ->with($analyticsEventMock, $entityId);

        $analyticsEventMock->expects($this->once())
            ->method('getId')
            ->willReturn($entityId);

        $result = $this->analyticsEventRepository->getById($entityId);

        $this->assertSame($analyticsEventMock, $result);
    }

    public function testGetByIdThrowsExceptionWhenNotFound(): void
    {
        $entityId = 999;
        $analyticsEventMock = $this->createMock(AnalyticsEvent::class);

        $this->analyticsEventFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($analyticsEventMock);

        $this->resourceModelMock->expects($this->once())
            ->method('load')
            ->with($analyticsEventMock, $entityId);

        $analyticsEventMock->expects($this->once())
            ->method('getId')
            ->willReturn(null);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Unable to find analytics event with ID "999"');

        $this->analyticsEventRepository->getById($entityId);
    }

    public function testDelete(): void
    {
        $analyticsEventMock = $this->createMock(AnalyticsEvent::class);

        $this->resourceModelMock->expects($this->once())
            ->method('delete')
            ->with($analyticsEventMock);

        $this->analyticsEventRepository->delete($analyticsEventMock);
    }

    public function testDeleteById(): void
    {
        $entityId = 456;
        $analyticsEventMock = $this->createMock(AnalyticsEvent::class);

        $this->analyticsEventFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($analyticsEventMock);

        $this->resourceModelMock->expects($this->once())
            ->method('load')
            ->with($analyticsEventMock, $entityId);

        $analyticsEventMock->expects($this->once())
            ->method('getId')
            ->willReturn($entityId);

        $this->resourceModelMock->expects($this->once())
            ->method('delete')
            ->with($analyticsEventMock);

        $this->analyticsEventRepository->deleteById($entityId);
    }

    public function testDeleteByIdThrowsExceptionWhenNotFound(): void
    {
        $entityId = 999;
        $analyticsEventMock = $this->createMock(AnalyticsEvent::class);

        $this->analyticsEventFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($analyticsEventMock);

        $this->resourceModelMock->expects($this->once())
            ->method('load')
            ->with($analyticsEventMock, $entityId);

        $analyticsEventMock->expects($this->once())
            ->method('getId')
            ->willReturn(null);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Unable to find analytics event with ID "999"');

        $this->analyticsEventRepository->deleteById($entityId);
    }
}
