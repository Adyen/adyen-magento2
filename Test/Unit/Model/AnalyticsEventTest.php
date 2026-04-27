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

namespace Adyen\Payment\Test\Unit\Model;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Model\AnalyticsEvent;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class AnalyticsEventTest extends AbstractAdyenTestCase
{
    private ?AnalyticsEvent $analyticsEvent;

    protected function setUp(): void
    {
        $this->analyticsEvent = $this->getMockBuilder(AnalyticsEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_init'])
            ->getMock();
    }

    protected function tearDown(): void
    {
        $this->analyticsEvent = null;
    }

    public function testSetAndGetUuid(): void
    {
        $uuid = 'test-uuid-12345';

        $result = $this->analyticsEvent->setUuid($uuid);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($uuid, $this->analyticsEvent->getUuid());
    }

    public function testSetAndGetRelationId(): void
    {
        $relationId = 'relation-id-67890';

        $result = $this->analyticsEvent->setRelationId($relationId);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($relationId, $this->analyticsEvent->getRelationId());
    }

    public function testSetAndGetType(): void
    {
        $type = 'error';

        $result = $this->analyticsEvent->setType($type);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($type, $this->analyticsEvent->getType());
    }

    public function testSetAndGetTopic(): void
    {
        $topic = 'payment_processing';

        $result = $this->analyticsEvent->setTopic($topic);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($topic, $this->analyticsEvent->getTopic());
    }

    public function testSetAndGetMessage(): void
    {
        $message = 'Test error message';

        $result = $this->analyticsEvent->setMessage($message);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($message, $this->analyticsEvent->getMessage());
    }

    public function testSetAndGetMessageWithNull(): void
    {
        $result = $this->analyticsEvent->setMessage(null);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertNull($this->analyticsEvent->getMessage());
    }

    public function testSetAndGetVersion(): void
    {
        $version = '1.0.0';

        $result = $this->analyticsEvent->setVersion($version);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($version, $this->analyticsEvent->getVersion());
    }

    public function testSetAndGetErrorType(): void
    {
        $errorType = 'InvalidArgumentException';

        $result = $this->analyticsEvent->setErrorType($errorType);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($errorType, $this->analyticsEvent->getErrorType());
    }

    public function testSetAndGetErrorTypeWithNull(): void
    {
        $result = $this->analyticsEvent->setErrorType(null);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertNull($this->analyticsEvent->getErrorType());
    }

    public function testSetAndGetErrorCode(): void
    {
        $errorCode = '500';

        $result = $this->analyticsEvent->setErrorCode($errorCode);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($errorCode, $this->analyticsEvent->getErrorCode());
    }

    public function testSetAndGetErrorCodeWithNull(): void
    {
        $result = $this->analyticsEvent->setErrorCode(null);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertNull($this->analyticsEvent->getErrorCode());
    }

    public function testSetAndGetErrorCount(): void
    {
        $errorCount = 3;

        $result = $this->analyticsEvent->setErrorCount($errorCount);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($errorCount, $this->analyticsEvent->getErrorCount());
    }

    public function testSetAndGetStatus(): void
    {
        $status = 'pending';

        $result = $this->analyticsEvent->setStatus($status);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($status, $this->analyticsEvent->getStatus());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $createdAt = '2025-01-02 14:00:00';

        $result = $this->analyticsEvent->setCreatedAt($createdAt);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($createdAt, $this->analyticsEvent->getCreatedAt());
    }

    public function testGetCreatedAtTimestamp(): void
    {
        $createdAt = '2025-01-02 14:00:00';
        $expectedTimestamp = strtotime($createdAt);

        $this->analyticsEvent->setCreatedAt($createdAt);

        $this->assertEquals($expectedTimestamp, $this->analyticsEvent->getCreatedAtTimestamp());
    }

    public function testSetAndGetUpdatedAt(): void
    {
        $updatedAt = '2025-01-02 15:00:00';

        $result = $this->analyticsEvent->setUpdatedAt($updatedAt);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($updatedAt, $this->analyticsEvent->getUpdatedAt());
    }

    public function testSetAndGetUpdatedAtWithNull(): void
    {
        $result = $this->analyticsEvent->setUpdatedAt(null);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertNull($this->analyticsEvent->getUpdatedAt());
    }

    public function testSetAndGetScheduledProcessingTime(): void
    {
        $scheduledProcessingTime = '2025-01-02 16:00:00';

        $result = $this->analyticsEvent->setScheduledProcessingTime($scheduledProcessingTime);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertEquals($scheduledProcessingTime, $this->analyticsEvent->getScheduledProcessingTime());
    }

    public function testSetAndGetScheduledProcessingTimeWithNull(): void
    {
        $result = $this->analyticsEvent->setScheduledProcessingTime(null);

        $this->assertInstanceOf(AnalyticsEventInterface::class, $result);
        $this->assertNull($this->analyticsEvent->getScheduledProcessingTime());
    }
}
