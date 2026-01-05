<?php

namespace Adyen\Payment\Test\Unit\Plugin;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Helper\AnalyticsEventState;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Plugin\RestApiReliabilityTracker;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Webapi\Controller\Rest\InputParamsResolver;
use Magento\Webapi\Controller\Rest\Router\Route;
use Magento\Webapi\Controller\Rest\SynchronousRequestProcessor;
use PHPUnit\Framework\MockObject\MockObject;

class RestApiReliabilityTrackerTest extends AbstractAdyenTestCase
{
    protected RestApiReliabilityTracker $restApiReliabilityTracker;
    protected AnalyticsEventState|MockObject $analyticsEventStateMock;
    protected InputParamsResolver|MockObject $inputParamsResolverMock;
    protected ManagerInterface|MockObject $eventManagerMock;
    protected Config|MockObject $configHelperMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected SynchronousRequestProcessor|MockObject $subjectMock;
    protected Request|MockObject $requestMock;
    protected Route|MockObject $routeMock;
    protected StoreInterface|MockObject $storeMock;

    public function setUp(): void
    {
        $this->analyticsEventStateMock = $this->createMock(AnalyticsEventState::class);
        $this->inputParamsResolverMock = $this->createMock(InputParamsResolver::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->subjectMock = $this->createMock(SynchronousRequestProcessor::class);
        $this->requestMock = $this->createMock(Request::class);
        $this->routeMock = $this->createMock(Route::class);
        $this->storeMock = $this->createMock(StoreInterface::class);

        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getId')->willReturn(1);
        $this->inputParamsResolverMock->method('getRoute')->willReturn($this->routeMock);

        $this->restApiReliabilityTracker = new RestApiReliabilityTracker(
            $this->analyticsEventStateMock,
            $this->inputParamsResolverMock,
            $this->eventManagerMock,
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->adyenLoggerMock
        );
    }

    public function testAroundProcessWhenReliabilityDataCollectionDisabled()
    {
        $expectedResult = 'result';

        $this->routeMock->method('getServiceClass')->willReturn('SomeClass');
        $this->routeMock->method('getServiceMethod')->willReturn('someMethod');

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(false);

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $this->eventManagerMock->expects($this->never())->method('dispatch');

        $result = $this->restApiReliabilityTracker->aroundProcess(
            $this->subjectMock,
            $proceed,
            $this->requestMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    public function testAroundProcessWhenActionNotTracked()
    {
        $expectedResult = 'result';

        $this->routeMock->method('getServiceClass')->willReturn('Some\Other\Namespace\Class');
        $this->routeMock->method('getServiceMethod')->willReturn('someMethod');

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $this->eventManagerMock->expects($this->never())->method('dispatch');

        $result = $this->restApiReliabilityTracker->aroundProcess(
            $this->subjectMock,
            $proceed,
            $this->requestMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider trackedClassNamesDataProvider
     */
    public function testAroundProcessDispatchesEventsForTrackedAdyenActions(string $className)
    {
        $expectedResult = 'result';
        $serviceMethod = 'testMethod';
        $relationId = 'test-relation-id';

        $this->routeMock->method('getServiceClass')->willReturn($className);
        $this->routeMock->method('getServiceMethod')->willReturn($serviceMethod);

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->analyticsEventStateMock
            ->expects($this->once())
            ->method('setTopic')
            ->with($serviceMethod);

        $this->analyticsEventStateMock
            ->method('getRelationId')
            ->willReturn($relationId);

        $this->eventManagerMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($eventName, $data) use ($serviceMethod, $relationId) {
                $this->assertEquals(AnalyticsEventState::EVENT_NAME, $eventName);
                $this->assertEquals($relationId, $data['data']['relationId']);
                $this->assertEquals($serviceMethod, $data['data']['topic']);
            });

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->restApiReliabilityTracker->aroundProcess(
            $this->subjectMock,
            $proceed,
            $this->requestMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    public static function trackedClassNamesDataProvider(): array
    {
        return [
            'Adyen Payment namespace' => ['Adyen\Payment\Api\SomeInterface'],
            'Adyen Express namespace' => ['Adyen\ExpressCheckout\Api\SomeInterface'],
            'Guest Shipping Information' => ['Magento\Checkout\Api\GuestShippingInformationManagementInterface'],
            'Shipping Information' => ['Magento\Checkout\Api\ShippingInformationManagementInterface'],
            'Guest Payment Information' => ['Magento\Checkout\Api\GuestPaymentInformationManagementInterface'],
            'Payment Information' => ['Magento\Checkout\Api\PaymentInformationManagementInterface'],
        ];
    }

    public function testAroundProcessHandlesAdyenExceptionWithExpectedEnd()
    {
        $className = 'Adyen\Payment\Api\SomeInterface';
        $serviceMethod = 'testMethod';
        $relationId = 'test-relation-id';
        $exception = new AdyenException('Test Adyen Exception');

        $this->routeMock->method('getServiceClass')->willReturn($className);
        $this->routeMock->method('getServiceMethod')->willReturn($serviceMethod);

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->analyticsEventStateMock
            ->method('getRelationId')
            ->willReturn($relationId);

        $dispatchCalls = [];
        $this->eventManagerMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($eventName, $data) use (&$dispatchCalls) {
                $dispatchCalls[] = $data['data']['type'];
            });

        $proceed = function ($request) use ($exception) {
            throw $exception;
        };

        $this->expectException(AdyenException::class);
        $this->expectExceptionMessage('Test Adyen Exception');

        try {
            $this->restApiReliabilityTracker->aroundProcess(
                $this->subjectMock,
                $proceed,
                $this->requestMock
            );
        } finally {
            $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_START->value, $dispatchCalls[0]);
            $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_END->value, $dispatchCalls[1]);
        }
    }

    public function testAroundProcessHandlesNotFoundExceptionWithExpectedEnd()
    {
        $className = 'Adyen\Payment\Api\SomeInterface';
        $serviceMethod = 'testMethod';
        $relationId = 'test-relation-id';
        $exception = new NotFoundException(__('Not Found'));

        $this->routeMock->method('getServiceClass')->willReturn($className);
        $this->routeMock->method('getServiceMethod')->willReturn($serviceMethod);

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->analyticsEventStateMock
            ->method('getRelationId')
            ->willReturn($relationId);

        $dispatchCalls = [];
        $this->eventManagerMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($eventName, $data) use (&$dispatchCalls) {
                $dispatchCalls[] = $data['data']['type'];
            });

        $proceed = function ($request) use ($exception) {
            throw $exception;
        };

        $this->expectException(NotFoundException::class);

        try {
            $this->restApiReliabilityTracker->aroundProcess(
                $this->subjectMock,
                $proceed,
                $this->requestMock
            );
        } finally {
            $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_START->value, $dispatchCalls[0]);
            $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_END->value, $dispatchCalls[1]);
        }
    }

    public function testAroundProcessHandlesValidatorExceptionWithExpectedEnd()
    {
        $className = 'Adyen\Payment\Api\SomeInterface';
        $serviceMethod = 'testMethod';
        $relationId = 'test-relation-id';
        $exception = new ValidatorException(__('Validation Failed'));

        $this->routeMock->method('getServiceClass')->willReturn($className);
        $this->routeMock->method('getServiceMethod')->willReturn($serviceMethod);

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->analyticsEventStateMock
            ->method('getRelationId')
            ->willReturn($relationId);

        $dispatchCalls = [];
        $this->eventManagerMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($eventName, $data) use (&$dispatchCalls) {
                $dispatchCalls[] = $data['data']['type'];
            });

        $proceed = function ($request) use ($exception) {
            throw $exception;
        };

        $this->expectException(ValidatorException::class);

        try {
            $this->restApiReliabilityTracker->aroundProcess(
                $this->subjectMock,
                $proceed,
                $this->requestMock
            );
        } finally {
            $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_START->value, $dispatchCalls[0]);
            $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_END->value, $dispatchCalls[1]);
        }
    }

    public function testAroundProcessHandlesUnexpectedExceptionWithUnexpectedEnd()
    {
        $className = 'Adyen\Payment\Api\SomeInterface';
        $serviceMethod = 'testMethod';
        $relationId = 'test-relation-id';
        $exceptionMessage = 'Unexpected error occurred';
        $exception = new \RuntimeException($exceptionMessage);

        $this->routeMock->method('getServiceClass')->willReturn($className);
        $this->routeMock->method('getServiceMethod')->willReturn($serviceMethod);

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->analyticsEventStateMock
            ->method('getRelationId')
            ->willReturn($relationId);

        $dispatchCalls = [];
        $this->eventManagerMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($eventName, $data) use (&$dispatchCalls, $exceptionMessage) {
                $dispatchCalls[] = [
                    'type' => $data['data']['type'],
                    'message' => $data['data']['message'] ?? null
                ];
            });

        $proceed = function ($request) use ($exception) {
            throw $exception;
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);

        try {
            $this->restApiReliabilityTracker->aroundProcess(
                $this->subjectMock,
                $proceed,
                $this->requestMock
            );
        } finally {
            $this->assertEquals(AnalyticsEventTypeEnum::EXPECTED_START->value, $dispatchCalls[0]['type']);
            $this->assertEquals(AnalyticsEventTypeEnum::UNEXPECTED_END->value, $dispatchCalls[1]['type']);
            $this->assertEquals($exceptionMessage, $dispatchCalls[1]['message']);
        }
    }

    public function testAroundProcessLogsErrorWhenInitialDispatchFails()
    {
        $className = 'Adyen\Payment\Api\SomeInterface';
        $serviceMethod = 'testMethod';
        $expectedResult = 'result';
        $dispatchException = new Exception('Dispatch failed');

        $this->routeMock->method('getServiceClass')->willReturn($className);
        $this->routeMock->method('getServiceMethod')->willReturn($serviceMethod);

        $this->configHelperMock
            ->method('isReliabilityDataCollectionEnabled')
            ->with(1)
            ->willReturn(true);

        $this->analyticsEventStateMock
            ->method('getRelationId')
            ->willReturn('test-relation-id');

        $callCount = 0;
        $this->eventManagerMock
            ->method('dispatch')
            ->willReturnCallback(function () use (&$callCount, $dispatchException) {
                $callCount++;
                if ($callCount === 1) {
                    throw $dispatchException;
                }
            });

        $this->adyenLoggerMock
            ->expects($this->once())
            ->method('error')
            ->with('Error occurred while dispatching analytics event: Dispatch failed');

        $proceed = function ($request) use ($expectedResult) {
            return $expectedResult;
        };

        $result = $this->restApiReliabilityTracker->aroundProcess(
            $this->subjectMock,
            $proceed,
            $this->requestMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    public function testIsActionTrackedReturnsTrueForAdyenPaymentNamespace()
    {
        $result = $this->invokeMethod(
            $this->restApiReliabilityTracker,
            'isActionTracked',
            ['Adyen\Payment\Api\SomeInterface']
        );

        $this->assertTrue($result);
    }

    public function testIsActionTrackedReturnsTrueForAdyenExpressNamespace()
    {
        $result = $this->invokeMethod(
            $this->restApiReliabilityTracker,
            'isActionTracked',
            ['Adyen\ExpressCheckout\Api\SomeInterface']
        );

        $this->assertTrue($result);
    }

    /**
     * @dataProvider magentoTrackedActionsDataProvider
     */
    public function testIsActionTrackedReturnsTrueForMagentoTrackedActions(string $className)
    {
        $result = $this->invokeMethod(
            $this->restApiReliabilityTracker,
            'isActionTracked',
            [$className]
        );

        $this->assertTrue($result);
    }

    public static function magentoTrackedActionsDataProvider(): array
    {
        return [
            ['Magento\Checkout\Api\GuestShippingInformationManagementInterface'],
            ['Magento\Checkout\Api\ShippingInformationManagementInterface'],
            ['Magento\Checkout\Api\GuestPaymentInformationManagementInterface'],
            ['Magento\Checkout\Api\PaymentInformationManagementInterface'],
        ];
    }

    public function testIsActionTrackedReturnsFalseForUntrackedClass()
    {
        $result = $this->invokeMethod(
            $this->restApiReliabilityTracker,
            'isActionTracked',
            ['Some\Other\Namespace\Class']
        );

        $this->assertFalse($result);
    }

    public function testIsActionTrackedReturnsFalseForPartialMagentoMatch()
    {
        $result = $this->invokeMethod(
            $this->restApiReliabilityTracker,
            'isActionTracked',
            ['Magento\Checkout\Api\SomeOtherInterface']
        );

        $this->assertFalse($result);
    }
}
