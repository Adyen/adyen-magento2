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

namespace Adyen\Payment\Test\Cron;

use Adyen\AdyenException;
use Adyen\Payment\Api\AnalyticsEventRepositoryInterface;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Api\Data\AnalyticsEventStatusEnum;
use Adyen\Payment\Cron\Providers\AnalyticsEventProviderInterface;
use Adyen\Payment\Cron\SubmitAnalyticsEvents;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class SubmitAnalyticsEventsTest extends AbstractAdyenTestCase
{
    protected ?SubmitAnalyticsEvents $submitAnalyticsEvents;
    protected CheckoutAnalytics|MockObject $checkoutAnalyticsHelperMock;
    protected AnalyticsEventRepositoryInterface|MockObject $analyticsEventRepositoryMock;
    protected Config|MockObject $configHelperMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected AnalyticsEventProviderInterface|MockObject $providerMock;
    protected StoreInterface|MockObject $storeMock;
    protected array $providers;

    protected function setUp(): void
    {
        $this->checkoutAnalyticsHelperMock = $this->createMock(CheckoutAnalytics::class);
        $this->analyticsEventRepositoryMock = $this->createMock(AnalyticsEventRepositoryInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->providerMock = $this->createMock(AnalyticsEventProviderInterface::class);
        $this->storeMock = $this->createMock(StoreInterface::class);

        $this->storeMock->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);

        $this->providers = [$this->providerMock];

        $this->submitAnalyticsEvents = new SubmitAnalyticsEvents(
            $this->providers,
            $this->checkoutAnalyticsHelperMock,
            $this->analyticsEventRepositoryMock,
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->submitAnalyticsEvents = null;
    }

    public function testExecuteWhenReliabilityDataCollectionDisabled()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(false);

        $this->providerMock->expects($this->never())->method('provide');
        $this->checkoutAnalyticsHelperMock->expects($this->never())->method('initiateCheckoutAttempt');
        $this->checkoutAnalyticsHelperMock->expects($this->never())->method('sendAnalytics');

        $this->submitAnalyticsEvents->execute();
    }

    public function testExecuteWithNoEventsFromProvider()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willReturn([]);

        $this->checkoutAnalyticsHelperMock->expects($this->never())->method('initiateCheckoutAttempt');
        $this->checkoutAnalyticsHelperMock->expects($this->never())->method('sendAnalytics');

        $this->submitAnalyticsEvents->execute();
    }

    public function testExecuteWithEventsSuccessfully()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock->method('getVersion')->willReturn('1.0.0');
        $analyticsEventMock->method('getErrorCount')->willReturn(0);

        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willReturn([$analyticsEventMock]);

        $this->providerMock->expects($this->once())
            ->method('getAnalyticsContext')
            ->willReturn(CheckoutAnalytics::CONTEXT_TYPE_INFO);

        $checkoutAttemptId = 'test-checkout-attempt-id';
        $this->checkoutAnalyticsHelperMock->expects($this->once())
            ->method('initiateCheckoutAttempt')
            ->with('1.0.0')
            ->willReturn($checkoutAttemptId);

        $analyticsEventMock->expects($this->exactly(2))
            ->method('setStatus');

        $this->analyticsEventRepositoryMock->expects($this->exactly(2))
            ->method('save')
            ->with($analyticsEventMock)
            ->willReturn($analyticsEventMock);

        $this->checkoutAnalyticsHelperMock->expects($this->once())
            ->method('sendAnalytics')
            ->with($checkoutAttemptId, $this->callback(function ($events) use ($analyticsEventMock) {
                return count($events) === 1 && $events[0] === $analyticsEventMock;
            }), CheckoutAnalytics::CONTEXT_TYPE_INFO)
            ->willReturn(['success' => true]);

        $this->submitAnalyticsEvents->execute();
    }

    public function testExecuteWithEventsAndApiError()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock->method('getVersion')->willReturn('1.0.0');
        $analyticsEventMock->method('getErrorCount')->willReturn(1);

        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willReturn([$analyticsEventMock]);

        $this->providerMock->expects($this->once())
            ->method('getAnalyticsContext')
            ->willReturn(CheckoutAnalytics::CONTEXT_TYPE_ERRORS);

        $checkoutAttemptId = 'test-checkout-attempt-id';
        $this->checkoutAnalyticsHelperMock->expects($this->once())
            ->method('initiateCheckoutAttempt')
            ->with('1.0.0')
            ->willReturn($checkoutAttemptId);

        $analyticsEventMock->expects($this->exactly(2))
            ->method('setStatus');

        $this->analyticsEventRepositoryMock->expects($this->exactly(2))
            ->method('save')
            ->with($analyticsEventMock)
            ->willReturn($analyticsEventMock);

        $this->checkoutAnalyticsHelperMock->expects($this->once())
            ->method('sendAnalytics')
            ->with($checkoutAttemptId, $this->callback(function ($events) use ($analyticsEventMock) {
                return count($events) === 1 && $events[0] === $analyticsEventMock;
            }), CheckoutAnalytics::CONTEXT_TYPE_ERRORS)
            ->willReturn(['error' => 'API error']);

        $analyticsEventMock->expects($this->once())
            ->method('setErrorCount')
            ->with(2);

        $analyticsEventMock->expects($this->once())
            ->method('setScheduledProcessingTime');

        $this->submitAnalyticsEvents->execute();
    }

    public function testExecuteWithEventsAndMaxErrorCountReached()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock->method('getVersion')->willReturn('1.0.0');
        $analyticsEventMock->method('getErrorCount')->willReturn(4);

        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willReturn([$analyticsEventMock]);

        $this->providerMock->expects($this->once())
            ->method('getAnalyticsContext')
            ->willReturn(CheckoutAnalytics::CONTEXT_TYPE_INFO);

        $checkoutAttemptId = 'test-checkout-attempt-id';
        $this->checkoutAnalyticsHelperMock->expects($this->once())
            ->method('initiateCheckoutAttempt')
            ->with('1.0.0')
            ->willReturn($checkoutAttemptId);

        $this->analyticsEventRepositoryMock->expects($this->exactly(2))
            ->method('save')
            ->with($analyticsEventMock)
            ->willReturn($analyticsEventMock);

        $this->checkoutAnalyticsHelperMock->expects($this->once())
            ->method('sendAnalytics')
            ->willReturn(['error' => 'API error']);

        $analyticsEventMock->expects($this->once())
            ->method('setErrorCount')
            ->with(5);

        $this->submitAnalyticsEvents->execute();
    }

    public function testExecuteWithMultipleEventsGroupedByVersion()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $analyticsEventMock1 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock1->method('getVersion')->willReturn('1.0.0');
        $analyticsEventMock1->method('getErrorCount')->willReturn(0);

        $analyticsEventMock2 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock2->method('getVersion')->willReturn('2.0.0');
        $analyticsEventMock2->method('getErrorCount')->willReturn(0);

        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willReturn([$analyticsEventMock1, $analyticsEventMock2]);

        $this->providerMock->expects($this->once())
            ->method('getAnalyticsContext')
            ->willReturn(CheckoutAnalytics::CONTEXT_TYPE_INFO);

        $this->checkoutAnalyticsHelperMock->expects($this->exactly(2))
            ->method('initiateCheckoutAttempt')
            ->willReturnMap([
                ['1.0.0', 'checkout-attempt-id-v1'],
                ['2.0.0', 'checkout-attempt-id-v2']
            ]);

        $this->analyticsEventRepositoryMock->expects($this->exactly(4))
            ->method('save')
            ->willReturnCallback(function ($event) {
                return $event;
            });

        $this->checkoutAnalyticsHelperMock->expects($this->exactly(2))
            ->method('sendAnalytics')
            ->willReturn(['success' => true]);

        $this->submitAnalyticsEvents->execute();
    }

    public function testExecuteWithMultipleProviders()
    {
        $providerMock2 = $this->createMock(AnalyticsEventProviderInterface::class);

        $submitAnalyticsEvents = new SubmitAnalyticsEvents(
            [$this->providerMock, $providerMock2],
            $this->checkoutAnalyticsHelperMock,
            $this->analyticsEventRepositoryMock,
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->adyenLoggerMock
        );

        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $analyticsEventMock1 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock1->method('getVersion')->willReturn('1.0.0');
        $analyticsEventMock1->method('getErrorCount')->willReturn(0);

        $analyticsEventMock2 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock2->method('getVersion')->willReturn('1.0.0');
        $analyticsEventMock2->method('getErrorCount')->willReturn(0);

        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willReturn([$analyticsEventMock1]);

        $this->providerMock->expects($this->once())
            ->method('getAnalyticsContext')
            ->willReturn(CheckoutAnalytics::CONTEXT_TYPE_INFO);

        $providerMock2->expects($this->once())
            ->method('provide')
            ->willReturn([$analyticsEventMock2]);

        $providerMock2->expects($this->once())
            ->method('getAnalyticsContext')
            ->willReturn(CheckoutAnalytics::CONTEXT_TYPE_ERRORS);

        $this->checkoutAnalyticsHelperMock->expects($this->exactly(2))
            ->method('initiateCheckoutAttempt')
            ->with('1.0.0')
            ->willReturn('checkout-attempt-id');

        $this->analyticsEventRepositoryMock->expects($this->exactly(4))
            ->method('save')
            ->willReturnCallback(function ($event) {
                return $event;
            });

        $this->checkoutAnalyticsHelperMock->expects($this->exactly(2))
            ->method('sendAnalytics')
            ->willReturn(['success' => true]);

        $submitAnalyticsEvents->execute();
    }

    public function testExecuteLogsErrorOnException()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $exceptionMessage = 'Test exception message';
        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willThrowException(new Exception($exceptionMessage));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with('Error while submitting analytics events: ' . $exceptionMessage);

        $this->submitAnalyticsEvents->execute();
    }

    public function testExecuteWithAdyenExceptionOnInitiateCheckoutAttempt()
    {
        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $analyticsEventMock = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock->method('getVersion')->willReturn('1.0.0');

        $this->providerMock->expects($this->once())
            ->method('provide')
            ->willReturn([$analyticsEventMock]);

        $this->providerMock->expects($this->once())
            ->method('getAnalyticsContext')
            ->willReturn(CheckoutAnalytics::CONTEXT_TYPE_INFO);

        $exceptionMessage = 'Adyen API error';
        $this->checkoutAnalyticsHelperMock->expects($this->once())
            ->method('initiateCheckoutAttempt')
            ->willThrowException(new AdyenException($exceptionMessage));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with('Error while submitting analytics events: ' . $exceptionMessage);

        $this->submitAnalyticsEvents->execute();
    }

    public function testGroupByVersionPrivateMethod()
    {
        $analyticsEventMock1 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock1->method('getVersion')->willReturn('1.0.0');

        $analyticsEventMock2 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock2->method('getVersion')->willReturn('1.0.0');

        $analyticsEventMock3 = $this->createMock(AnalyticsEventInterface::class);
        $analyticsEventMock3->method('getVersion')->willReturn('2.0.0');

        $events = [$analyticsEventMock1, $analyticsEventMock2, $analyticsEventMock3];

        $result = $this->invokeMethod($this->submitAnalyticsEvents, 'groupByVersion', [$events]);

        $this->assertArrayHasKey('1.0.0', $result);
        $this->assertArrayHasKey('2.0.0', $result);
        $this->assertCount(2, $result['1.0.0']);
        $this->assertCount(1, $result['2.0.0']);
    }
}
