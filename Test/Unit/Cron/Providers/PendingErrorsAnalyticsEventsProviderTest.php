<?php

namespace Adyen\Payment\Test\Cron\Providers;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Cron\Providers\PendingErrorsAnalyticsEventsProvider;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\Collection as AnalyticsEventCollection;
use Adyen\Payment\Model\ResourceModel\AnalyticsEvent\CollectionFactory as AnalyticsEventCollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class PendingErrorsAnalyticsEventsProviderTest extends AbstractAdyenTestCase
{
    protected PendingErrorsAnalyticsEventsProvider $pendingErrorsAnalyticsEventsProvider;
    protected AnalyticsEventCollectionFactory|MockObject $analyticsEventCollectionFactoryMock;
    protected AnalyticsEventCollection|MockObject $analyticsEventCollectionMock;

    public function setUp(): void
    {
        $this->analyticsEventCollectionFactoryMock = $this->createMock(AnalyticsEventCollectionFactory::class);
        $this->analyticsEventCollectionMock = $this->createMock(AnalyticsEventCollection::class);

        $this->analyticsEventCollectionFactoryMock
            ->method('create')
            ->willReturn($this->analyticsEventCollectionMock);

        $this->pendingErrorsAnalyticsEventsProvider = new PendingErrorsAnalyticsEventsProvider(
            $this->analyticsEventCollectionFactoryMock
        );
    }

    public function testProvideReturnsEmptyArrayWhenNoEvents()
    {
        $this->analyticsEventCollectionMock
            ->method('pendingAnalyticsEvents')
            ->with([AnalyticsEventTypeEnum::UNEXPECTED_END])
            ->willReturn($this->analyticsEventCollectionMock);

        $this->analyticsEventCollectionMock
            ->method('getItems')
            ->willReturn([]);

        $result = $this->pendingErrorsAnalyticsEventsProvider->provide();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProvideReturnsAnalyticsEventsSuccessfully()
    {
        $analyticsEventMock1 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock2 = $this->createMock(AnalyticsEventInterface::class);
        $expectedEvents = [$analyticsEventMock1, $analyticsEventMock2];

        $this->analyticsEventCollectionMock
            ->method('pendingAnalyticsEvents')
            ->with([AnalyticsEventTypeEnum::UNEXPECTED_END])
            ->willReturn($this->analyticsEventCollectionMock);

        $this->analyticsEventCollectionMock
            ->method('getItems')
            ->willReturn($expectedEvents);

        $result = $this->pendingErrorsAnalyticsEventsProvider->provide();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing($expectedEvents, $result);
    }

    public function testGetProviderNameReturnsCorrectName()
    {
        $expectedName = 'Pending analytics events for `errors` context';

        $result = $this->pendingErrorsAnalyticsEventsProvider->getProviderName();

        $this->assertEquals($expectedName, $result);
    }

    public function testGetAnalyticsContextReturnsErrorsContext()
    {
        $result = $this->pendingErrorsAnalyticsEventsProvider->getAnalyticsContext();

        $this->assertEquals(CheckoutAnalytics::CONTEXT_TYPE_ERRORS, $result);
    }
}
