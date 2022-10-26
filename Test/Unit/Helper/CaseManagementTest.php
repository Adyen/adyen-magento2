<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Unit\Helper;

use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Framework\Serialize\SerializerInterface;

class CaseManagementTest extends AbstractAdyenTestCase
{
    /** @var Notification */
    private $mockNotification;

    /** @var SerializerInterface */
    private $mockSerializer;

    public function setUp(): void
    {
        $this->mockSerializer = $this->createMock(SerializerInterface::class);
        $this->mockNotification = $this->createConfiguredMock(Notification::class, [
            'getAdditionalData' => [
                CaseManagement::FRAUD_MANUAL_REVIEW => 'true'
            ]
        ]);
    }

    public function testRequiresManualReviewTrue()
    {
        $this->mockSerializer->method('unserialize')->willReturn([
            CaseManagement::FRAUD_MANUAL_REVIEW => 'true'
        ]);

        $caseManagementHelper = new CaseManagement(
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class),
            $this->mockSerializer
        );
        $this->assertTrue($caseManagementHelper->requiresManualReview($this->mockNotification));
    }

    public function testRequiresManualReviewNoFraudKey()
    {
        $this->mockSerializer->method('unserialize')->willReturn([
            'test' => 'myPatience'
        ]);

        $caseManagementHelper = new CaseManagement(
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class),
            $this->mockSerializer
        );

        $this->assertFalse($caseManagementHelper->requiresManualReview($this->mockNotification));
    }

    public function testRequiresManualReviewUnexpectedValue()
    {
        $this->mockSerializer->method('unserialize')->willReturn([
            CaseManagement::FRAUD_MANUAL_REVIEW => 1
        ]);

        $caseManagementHelper = new CaseManagement(
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class),
            $this->mockSerializer
        );

        $this->assertFalse($caseManagementHelper->requiresManualReview($this->mockNotification));
    }
}
