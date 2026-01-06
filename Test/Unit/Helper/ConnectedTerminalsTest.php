<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\Session;
use Exception;

class ConnectedTerminalsTest extends AbstractAdyenTestCase
{
    private $dataHelper;
    private $session;
    private $adyenLogger;
    private $connectedTerminals;

    protected function setUp(): void
    {
        $this->dataHelper = $this->createMock(Data::class);
        $this->session = $this->createMock(Session::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);

        $this->connectedTerminals = new ConnectedTerminals(
            $this->dataHelper,
            $this->session,
            $this->adyenLogger
        );
    }

    public function testGetConnectedTerminalsApiResponseReturnsCachedResponse(): void
    {
        $expectedResponse = ['uniqueTerminalIds' => ['terminal1', 'terminal2']];

        // Use reflection to set the protected property directly
        $this->invokeMethod($this->connectedTerminals, 'setConnectedTerminalsApiResponse', [$expectedResponse]);

        $result = $this->connectedTerminals->getConnectedTerminalsApiResponse();

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetConnectedTerminalsApiResponseReturnsNullOnException(): void
    {
        // Create a partial mock to make getConnectedTerminals throw an exception
        $connectedTerminals = $this->getMockBuilder(ConnectedTerminals::class)
            ->setConstructorArgs([
                $this->dataHelper,
                $this->session,
                $this->adyenLogger
            ])
            ->onlyMethods(['getConnectedTerminals'])
            ->getMock();

        $connectedTerminals->method('getConnectedTerminals')
            ->willThrowException(new Exception('Test exception'));

        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('An error occurred while trying to get connected terminals'));

        $result = $connectedTerminals->getConnectedTerminalsApiResponse(1);

        $this->assertNull($result);
    }

    public function testGetConnectedTerminalsApiResponseCallsGetConnectedTerminalsWhenNotCached(): void
    {
        $storeId = 1;
        $expectedResponse = ['uniqueTerminalIds' => ['terminal1']];

        // Create a partial mock to control getConnectedTerminals behavior
        $connectedTerminals = $this->getMockBuilder(ConnectedTerminals::class)
            ->setConstructorArgs([
                $this->dataHelper,
                $this->session,
                $this->adyenLogger
            ])
            ->onlyMethods(['getConnectedTerminals'])
            ->getMock();

        $connectedTerminals->expects($this->once())
            ->method('getConnectedTerminals')
            ->with($storeId)
            ->willReturn($expectedResponse);

        $result = $connectedTerminals->getConnectedTerminalsApiResponse($storeId);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetConnectedTerminalsApiResponseDoesNotCallGetConnectedTerminalsWhenCached(): void
    {
        $cachedResponse = ['uniqueTerminalIds' => ['cachedTerminal']];

        // Create a partial mock to verify getConnectedTerminals is not called
        $connectedTerminals = $this->getMockBuilder(ConnectedTerminals::class)
            ->setConstructorArgs([
                $this->dataHelper,
                $this->session,
                $this->adyenLogger
            ])
            ->onlyMethods(['getConnectedTerminals'])
            ->getMock();

        // Set the cached response using reflection
        $this->invokeMethod($connectedTerminals, 'setConnectedTerminalsApiResponse', [$cachedResponse]);

        $connectedTerminals->expects($this->never())
            ->method('getConnectedTerminals');

        $result = $connectedTerminals->getConnectedTerminalsApiResponse(1);

        $this->assertEquals($cachedResponse, $result);
    }

    public function testSetConnectedTerminalsApiResponseSetsCache(): void
    {
        $response = ['uniqueTerminalIds' => ['terminal1', 'terminal2', 'terminal3']];

        $this->invokeMethod($this->connectedTerminals, 'setConnectedTerminalsApiResponse', [$response]);

        // Verify by calling getConnectedTerminalsApiResponse which should return cached value
        // We need to use reflection to read the protected property
        $reflection = new \ReflectionClass($this->connectedTerminals);
        $property = $reflection->getProperty('connectedTerminalsApiResponse');
        $property->setAccessible(true);

        $this->assertEquals($response, $property->getValue($this->connectedTerminals));
    }

    public function testSetConnectedTerminalsApiResponseOverwritesExistingCache(): void
    {
        $initialResponse = ['uniqueTerminalIds' => ['terminal1']];
        $newResponse = ['uniqueTerminalIds' => ['terminal2', 'terminal3']];

        $this->invokeMethod($this->connectedTerminals, 'setConnectedTerminalsApiResponse', [$initialResponse]);
        $this->invokeMethod($this->connectedTerminals, 'setConnectedTerminalsApiResponse', [$newResponse]);

        $reflection = new \ReflectionClass($this->connectedTerminals);
        $property = $reflection->getProperty('connectedTerminalsApiResponse');
        $property->setAccessible(true);

        $this->assertEquals($newResponse, $property->getValue($this->connectedTerminals));
    }

    public function testSetConnectedTerminalsApiResponseWithEmptyArray(): void
    {
        $emptyResponse = [];

        $this->invokeMethod($this->connectedTerminals, 'setConnectedTerminalsApiResponse', [$emptyResponse]);

        $reflection = new \ReflectionClass($this->connectedTerminals);
        $property = $reflection->getProperty('connectedTerminalsApiResponse');
        $property->setAccessible(true);

        $this->assertEquals($emptyResponse, $property->getValue($this->connectedTerminals));
    }

    public function testGetConnectedTerminalsApiResponseWithNullStoreId(): void
    {
        $expectedResponse = ['uniqueTerminalIds' => ['terminal1']];

        $connectedTerminals = $this->getMockBuilder(ConnectedTerminals::class)
            ->setConstructorArgs([
                $this->dataHelper,
                $this->session,
                $this->adyenLogger
            ])
            ->onlyMethods(['getConnectedTerminals'])
            ->getMock();

        $connectedTerminals->expects($this->once())
            ->method('getConnectedTerminals')
            ->with(null)
            ->willReturn($expectedResponse);

        $result = $connectedTerminals->getConnectedTerminalsApiResponse(null);

        $this->assertEquals($expectedResponse, $result);
    }
}
