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

namespace Adyen\Payment\Test\Plugin;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Helper\AnalyticsEventState;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Plugin\FrontendControllerReliabilityTracker;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Magento\Framework\App\FrontController;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use RuntimeException;

class FrontendControllerReliabilityTrackerTest extends AbstractAdyenTestCase
{
    protected ?FrontendControllerReliabilityTracker $frontendControllerReliabilityTracker;
    protected AnalyticsEventState $analyticsEventStateMock;
    protected ManagerInterface $eventManagerMock;
    protected Config $configHelperMock;
    protected StoreManagerInterface $storeManagerMock;
    protected FrontController $frontControllerMock;
    protected HttpRequest $requestMock;

    protected function setUp(): void
    {
        $this->analyticsEventStateMock = $this->createMock(AnalyticsEventState::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->frontControllerMock = $this->createMock(FrontController::class);
        $this->requestMock = $this->createMock(HttpRequest::class);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->frontendControllerReliabilityTracker = new FrontendControllerReliabilityTracker(
            $this->analyticsEventStateMock,
            $this->eventManagerMock,
            $this->configHelperMock,
            $this->storeManagerMock
        );
    }

    protected function tearDown(): void
    {
        $this->frontendControllerReliabilityTracker = null;
    }

    public function testAroundDispatchWithNonAdyenUri()
    {
        $expectedResult = 'result';
        $serviceUri = '/checkout/cart/';

        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getPathInfo')
            ->willReturn($serviceUri);

        $this->eventManagerMock->expects($this->never())->method('dispatch');

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->frontendControllerReliabilityTracker->aroundDispatch(
            $this->frontControllerMock,
            $proceed,
            $this->requestMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    public function testAroundDispatchWithReliabilityDataCollectionDisabled()
    {
        $expectedResult = 'result';
        $serviceUri = '/adyen/return';

        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(false);

        $this->requestMock->expects($this->once())
            ->method('getPathInfo')
            ->willReturn($serviceUri);

        $this->eventManagerMock->expects($this->never())->method('dispatch');

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->frontendControllerReliabilityTracker->aroundDispatch(
            $this->frontControllerMock,
            $proceed,
            $this->requestMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    public function testAroundDispatchWithAdyenUriSuccessfulExecution()
    {
        $expectedResult = 'result';
        $serviceUri = '/adyen/return';
        $relationId = 'test-relation-id';

        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getPathInfo')
            ->willReturn($serviceUri);

        $this->analyticsEventStateMock->expects($this->once())
            ->method('setTopic')
            ->with($serviceUri);

        $this->analyticsEventStateMock->expects($this->exactly(2))
            ->method('getRelationId')
            ->willReturn($relationId);

        $this->eventManagerMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with(
                AnalyticsEventState::EVENT_NAME,
                $this->callback(function ($data) use ($relationId, $serviceUri) {
                    return isset($data['data']['relationId']) &&
                        $data['data']['relationId'] === $relationId &&
                        $data['data']['topic'] === $serviceUri;
                })
            );

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->frontendControllerReliabilityTracker->aroundDispatch(
            $this->frontControllerMock,
            $proceed,
            $this->requestMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider expectedExceptionProvider
     */
    public function testAroundDispatchWithExpectedException(string $exceptionClass)
    {
        $serviceUri = '/adyen/return';
        $relationId = 'test-relation-id';

        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getPathInfo')
            ->willReturn($serviceUri);

        $this->analyticsEventStateMock->expects($this->once())
            ->method('setTopic')
            ->with($serviceUri);

        $this->analyticsEventStateMock->expects($this->exactly(2))
            ->method('getRelationId')
            ->willReturn($relationId);

        $dispatchCallCount = 0;
        $this->eventManagerMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with(
                AnalyticsEventState::EVENT_NAME,
                $this->callback(function ($data) use ($relationId, $serviceUri, &$dispatchCallCount) {
                    $dispatchCallCount++;
                    if ($dispatchCallCount === 1) {
                        return $data['data']['type'] === AnalyticsEventTypeEnum::EXPECTED_START->value;
                    }
                    return $data['data']['type'] === AnalyticsEventTypeEnum::EXPECTED_END->value;
                })
            );

        if ($exceptionClass === LocalizedException::class) {
            $exception = new $exceptionClass(__('Test exception'));
        } else {
            $exception = new $exceptionClass('Test exception');
        }
        $proceed = function ($request) use ($exception) {
            throw $exception;
        };

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage('Test exception');

        $this->frontendControllerReliabilityTracker->aroundDispatch(
            $this->frontControllerMock,
            $proceed,
            $this->requestMock
        );
    }

    public static function expectedExceptionProvider(): array
    {
        return [
            'AdyenException' => [AdyenException::class],
            'InvalidDataException' => [InvalidDataException::class],
            'AuthenticationException' => [AuthenticationException::class],
            'LocalizedException' => [LocalizedException::class],
        ];
    }

    public function testAroundDispatchWithUnexpectedException()
    {
        $serviceUri = '/adyen/webhook';
        $relationId = 'test-relation-id';
        $exceptionMessage = 'Unexpected error occurred';

        $this->configHelperMock->expects($this->once())
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getPathInfo')
            ->willReturn($serviceUri);

        $this->analyticsEventStateMock->expects($this->once())
            ->method('setTopic')
            ->with($serviceUri);

        $this->analyticsEventStateMock->expects($this->exactly(2))
            ->method('getRelationId')
            ->willReturn($relationId);

        $dispatchCallCount = 0;
        $this->eventManagerMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with(
                AnalyticsEventState::EVENT_NAME,
                $this->callback(function ($data) use ($relationId, $serviceUri, $exceptionMessage, &$dispatchCallCount) {
                    $dispatchCallCount++;
                    if ($dispatchCallCount === 1) {
                        return $data['data']['type'] === AnalyticsEventTypeEnum::EXPECTED_START->value;
                    }
                    return $data['data']['type'] === AnalyticsEventTypeEnum::UNEXPECTED_END->value &&
                        $data['data']['message'] === $exceptionMessage;
                })
            );

        $proceed = function ($request) use ($exceptionMessage) {
            throw new RuntimeException($exceptionMessage);
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->frontendControllerReliabilityTracker->aroundDispatch(
            $this->frontControllerMock,
            $proceed,
            $this->requestMock
        );
    }

    public function testAroundDispatchDispatchesCorrectEventDataOnStart()
    {
        $serviceUri = '/adyen/webhook/';
        $relationId = 'unique-relation-id';
        $expectedResult = 'success';

        $this->configHelperMock->method('isReliabilityDataCollectionEnabled')->willReturn(true);
        $this->requestMock->method('getPathInfo')->willReturn($serviceUri);
        $this->analyticsEventStateMock->method('getRelationId')->willReturn($relationId);

        $capturedEventData = [];
        $this->eventManagerMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($eventName, $data) use (&$capturedEventData) {
                $capturedEventData[] = $data;
            });

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $this->frontendControllerReliabilityTracker->aroundDispatch(
            $this->frontControllerMock,
            $proceed,
            $this->requestMock
        );

        $this->assertCount(2, $capturedEventData);

        $startEventData = $capturedEventData[0]['data'];
        $this->assertEquals($relationId, $startEventData['relationId']);
        $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_START->value, $startEventData['type']);
        $this->assertEquals($serviceUri, $startEventData['topic']);

        $endEventData = $capturedEventData[1]['data'];
        $this->assertEquals($relationId, $endEventData['relationId']);
        $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_END->value, $endEventData['type']);
        $this->assertEquals($serviceUri, $endEventData['topic']);
    }

    public function testAdyenControllerUriConstant()
    {
        $this->assertEquals('/adyen/', FrontendControllerReliabilityTracker::ADYEN_CONTROLLER_URI);
    }
}
