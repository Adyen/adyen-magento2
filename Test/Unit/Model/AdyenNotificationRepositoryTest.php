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

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Model\AdyenNotificationRepository;
use Adyen\Payment\Model\Notification as NotificationModel;
use Adyen\Payment\Model\ResourceModel\Notification;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenNotificationRepositoryTest extends AbstractAdyenTestCase
{
    protected ?AdyenNotificationRepository $adyenNotificationRepository;
    protected SearchResultFactory|MockObject $searchResultFactoryMock;
    protected CollectionFactory|MockObject $collectionFactoryMock;
    protected CollectionProcessor|MockObject $collectionProcessorMock;
    protected ObjectManagerInterface|MockObject $objectManagerMock;

    const RESOURCE_MODEL = 'Adyen\Payment\Model\ResourceModel\Notification';

    protected function setUp(): void
    {
        $this->searchResultFactoryMock = $this->createMock(SearchResultFactory::class);
        $this->collectionFactoryMock = $this->createGeneratedMock(
            CollectionFactory::class,
            ['create']
        );
        $this->collectionProcessorMock = $this->createMock(CollectionProcessor::class);
        $this->objectManagerMock = $this->createMock(ObjectManagerInterface::class);

        $this->adyenNotificationRepository = new AdyenNotificationRepository(
            $this->objectManagerMock,
            self::RESOURCE_MODEL
        );
    }

    protected function tearDown(): void
    {
        $this->adyenNotificationRepository = null;
    }

    public function testDeleteByIds()
    {
        $entityIds = ['1', '2', '3'];

        $resourceModel = $this->createMock(Notification::class);

        // Assert method call `deleteByIds()`
        $resourceModel->expects($this->once())->method('deleteByIds')->with($entityIds);

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(self::RESOURCE_MODEL)
            ->willReturn($resourceModel);

        $this->adyenNotificationRepository->deleteByIds($entityIds);
    }

    public function testDeleteByIdsEmptyValues()
    {
        $entityIds = [];

        $resourceModel = $this->createMock(Notification::class);

        // Assert method not call `deleteByIds()`
        $resourceModel->expects($this->never())->method('deleteByIds');
        $this->objectManagerMock->expects($this->never())->method('get');

        $this->adyenNotificationRepository->deleteByIds($entityIds);
    }

    public function testSave()
    {
        $notification = $this->createMock(NotificationModel::class);
        $resourceModel = $this->createMock(Notification::class);

        $resourceModel->expects($this->once())
            ->method('save')
            ->with($notification)
            ->willReturnSelf();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(self::RESOURCE_MODEL)
            ->willReturn($resourceModel);

        $result = $this->adyenNotificationRepository->save($notification);
        $this->assertInstanceOf(NotificationInterface::class, $result);
    }
}
