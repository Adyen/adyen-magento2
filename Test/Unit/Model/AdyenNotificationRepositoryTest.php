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

use Adyen\Payment\Model\AdyenNotificationRepository;
use Adyen\Payment\Model\Notification as NotificationEntity;
use Adyen\Payment\Model\ResourceModel\Notification;
use Adyen\Payment\Model\ResourceModel\Notification\Collection;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
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
            $this->searchResultFactoryMock,
            $this->collectionFactoryMock,
            $this->collectionProcessorMock,
            $this->objectManagerMock,
            self::RESOURCE_MODEL
        );
    }

    protected function tearDown(): void
    {
        $this->adyenNotificationRepository = null;
    }

    public function testGetList()
    {
        $searchResult = $this->createMock(SearchResultInterface::class);
        $searchResult->expects($this->once())->method('setItems');
        $searchResult->expects($this->once())->method('setTotalCount');

        $this->searchResultFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($searchResult);

        $collection = $this->createMock(Collection::class);
        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $this->collectionProcessorMock->expects($this->once())
            ->method('process')
            ->with($searchCriteria, $collection);


        $result = $this->adyenNotificationRepository->getList($searchCriteria);
        $this->assertInstanceOf(SearchResultsInterface::class, $result);
    }

    public function testDelete()
    {
        $entityMock = $this->createMock(NotificationEntity::class);

        $resourceModelMock = $this->createMock(Notification::class);
        $resourceModelMock->expects($this->once())->method('delete')->with($entityMock);

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->willReturn($resourceModelMock);

        $result = $this->adyenNotificationRepository->delete($entityMock);

        $this->assertTrue($result);
    }
}
