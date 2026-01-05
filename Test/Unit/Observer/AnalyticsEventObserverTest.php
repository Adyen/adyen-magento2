<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Api\AnalyticsEventRepositoryInterface;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AnalyticsEventFactory;
use Adyen\Payment\Observer\AnalyticsEventObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class AnalyticsEventObserverTest extends AbstractAdyenTestCase
{
    private AnalyticsEventRepositoryInterface $analyticsRepositoryMock;
    private AnalyticsEventFactory $analyticsEventFactoryMock;
    private PlatformInfo $platformInfoMock;
    private Config $configHelperMock;
    private StoreManagerInterface $storeManagerMock;
    private AdyenLogger $adyenLoggerMock;
    private AnalyticsEventObserver $analyticsEventObserver;
    private Observer $observerMock;
    private Event $eventMock;
    private StoreInterface $storeMock;

    protected function setUp(): void
    {
        $this->analyticsRepositoryMock = $this->createMock(AnalyticsEventRepositoryInterface::class);
        $this->analyticsEventFactoryMock = $this->createGeneratedMock(AnalyticsEventFactory::class, ['create']);
        $this->platformInfoMock = $this->createMock(PlatformInfo::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->observerMock = $this->createMock(Observer::class);
        $this->eventMock = $this->getMockBuilder(Event::class)
            ->onlyMethods(['getData'])
            ->getMock();
        $this->storeMock = $this->createMock(StoreInterface::class);

        $this->analyticsEventObserver = new AnalyticsEventObserver(
            $this->analyticsRepositoryMock,
            $this->analyticsEventFactoryMock,
            $this->platformInfoMock,
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->adyenLoggerMock
        );
    }

    public function testExecuteWhenReliabilityDataCollectionIsDisabled(): void
    {
        $storeId = 1;

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->configHelperMock->method('isReliabilityDataCollectionEnabled')
            ->with($storeId)
            ->willReturn(false);

        $this->analyticsEventFactoryMock->expects($this->never())->method('create');
        $this->analyticsRepositoryMock->expects($this->never())->method('save');

        $this->analyticsEventObserver->execute($this->observerMock);
    }

    public function testExecuteWhenReliabilityDataCollectionIsEnabledWithoutMessage(): void
    {
        $storeId = 1;
        $moduleVersion = '9.0.0';
        $eventData = [
            'relationId' => 'test-relation-id',
            'type' => 'test-type',
            'topic' => 'test-topic'
        ];

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->configHelperMock->method('isReliabilityDataCollectionEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getData')->with('data')->willReturn($eventData);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $this->analyticsEventFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($analyticsEventMock);

        $this->platformInfoMock->method('getModuleVersion')->willReturn($moduleVersion);

        $analyticsEventMock->expects($this->once())
            ->method('setUuid')
            ->with($this->matchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/'));
        $analyticsEventMock->expects($this->once())
            ->method('setRelationId')
            ->with($eventData['relationId']);
        $analyticsEventMock->expects($this->once())
            ->method('setType')
            ->with($eventData['type']);
        $analyticsEventMock->expects($this->once())
            ->method('setTopic')
            ->with($eventData['topic']);
        $analyticsEventMock->expects($this->once())
            ->method('setVersion')
            ->with($moduleVersion);
        $analyticsEventMock->expects($this->never())
            ->method('setMessage');

        $this->analyticsRepositoryMock->expects($this->once())
            ->method('save')
            ->with($analyticsEventMock);

        $this->analyticsEventObserver->execute($this->observerMock);
    }

    public function testExecuteWhenReliabilityDataCollectionIsEnabledWithMessage(): void
    {
        $storeId = 1;
        $moduleVersion = '9.0.0';
        $eventData = [
            'relationId' => 'test-relation-id',
            'type' => 'test-type',
            'topic' => 'test-topic',
            'message' => 'test-message'
        ];

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->configHelperMock->method('isReliabilityDataCollectionEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getData')->with('data')->willReturn($eventData);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $this->analyticsEventFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($analyticsEventMock);

        $this->platformInfoMock->method('getModuleVersion')->willReturn($moduleVersion);

        $analyticsEventMock->expects($this->once())
            ->method('setUuid')
            ->with($this->matchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/'));
        $analyticsEventMock->expects($this->once())
            ->method('setRelationId')
            ->with($eventData['relationId']);
        $analyticsEventMock->expects($this->once())
            ->method('setType')
            ->with($eventData['type']);
        $analyticsEventMock->expects($this->once())
            ->method('setTopic')
            ->with($eventData['topic']);
        $analyticsEventMock->expects($this->once())
            ->method('setVersion')
            ->with($moduleVersion);
        $analyticsEventMock->expects($this->once())
            ->method('setMessage')
            ->with($eventData['message']);

        $this->analyticsRepositoryMock->expects($this->once())
            ->method('save')
            ->with($analyticsEventMock);

        $this->analyticsEventObserver->execute($this->observerMock);
    }

    public function testExecuteLogsErrorWhenExceptionOccurs(): void
    {
        $storeId = 1;
        $exceptionMessage = 'Test exception message';
        $eventData = [
            'relationId' => 'test-relation-id',
            'type' => 'test-type',
            'topic' => 'test-topic'
        ];

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->configHelperMock->method('isReliabilityDataCollectionEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getData')->with('data')->willReturn($eventData);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $this->analyticsEventFactoryMock->method('create')->willReturn($analyticsEventMock);
        $this->platformInfoMock->method('getModuleVersion')->willReturn('9.0.0');

        $this->analyticsRepositoryMock->method('save')
            ->willThrowException(new Exception($exceptionMessage));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with('Error processing payment_method_adyen_analytics event: ' . $exceptionMessage);

        $this->analyticsEventObserver->execute($this->observerMock);
    }

    public function testExecuteLogsErrorWhenEventDataIsMissing(): void
    {
        $storeId = 1;

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->configHelperMock->method('isReliabilityDataCollectionEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getData')->with('data')->willReturn(null);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $this->analyticsEventFactoryMock->method('create')->willReturn($analyticsEventMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error processing payment_method_adyen_analytics event:'));

        $this->analyticsEventObserver->execute($this->observerMock);
    }
}
