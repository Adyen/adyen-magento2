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

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\AnalyticsEventState;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class AnalyticsEventStateTest extends AbstractAdyenTestCase
{
    private AnalyticsEventState $analyticsEventState;

    protected function setUp(): void
    {
        $this->analyticsEventState = new AnalyticsEventState();
    }

    public function testEventNameConstant(): void
    {
        $this->assertEquals('adyen_analytics_event', AnalyticsEventState::EVENT_NAME);
    }

    public function testGetRelationIdGeneratesUuidWhenNotSet(): void
    {
        $relationId = $this->analyticsEventState->getRelationId();

        $this->assertNotNull($relationId);
        $this->assertIsString($relationId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $relationId
        );
    }

    public function testGetRelationIdReturnsSameValueOnSubsequentCalls(): void
    {
        $firstCall = $this->analyticsEventState->getRelationId();
        $secondCall = $this->analyticsEventState->getRelationId();

        $this->assertSame($firstCall, $secondCall);
    }

    public function testGetTopicReturnsNullByDefault(): void
    {
        $this->assertNull($this->analyticsEventState->getTopic());
    }

    public function testSetTopicAndGetTopic(): void
    {
        $topic = 'test_topic';

        $this->analyticsEventState->setTopic($topic);

        $this->assertEquals($topic, $this->analyticsEventState->getTopic());
    }

    public function testSetTopicWithNullValue(): void
    {
        $this->analyticsEventState->setTopic('initial_topic');
        $this->analyticsEventState->setTopic(null);

        $this->assertNull($this->analyticsEventState->getTopic());
    }

    public function testSetTopicOverwritesPreviousValue(): void
    {
        $this->analyticsEventState->setTopic('first_topic');
        $this->analyticsEventState->setTopic('second_topic');

        $this->assertEquals('second_topic', $this->analyticsEventState->getTopic());
    }
}
