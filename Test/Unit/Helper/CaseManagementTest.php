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
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;

class CaseManagementTest extends AbstractAdyenTestCase
{
    /**
     * @var CaseManagement
     */
    private $caseManagementHelper;

    public function setUp(): void
    {
        $this->caseManagementHelper = new CaseManagement(
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class),
            $this->createMock(SerializerInterface::class)
        );
    }

    public function testRequiresManualReviewTrue()
    {
        $additionalData = $this->createConfiguredMock(Notification::class, [
            'getAdditionalData' => json_encode([
                CaseManagement::FRAUD_MANUAL_REVIEW => 'true'
            ])
        ]);

        $this->assertTrue($this->caseManagementHelper->requiresManualReview($additionalData));
    }

    public function testRequiresManualReviewNoFraudKey()
    {
        $additionalData = $this->createConfiguredMock(Notification::class, [
            'getAdditionalData' => json_encode([
                'test' => 'myPatience'
            ])
        ]);

        $this->assertFalse($this->caseManagementHelper->requiresManualReview($additionalData));
    }

    public function testRequiresManualReviewUnexpectedValue()
    {
        $additionalData = $this->createConfiguredMock(Notification::class, [
            'getAdditionalData' => json_encode([
                CaseManagement::FRAUD_MANUAL_REVIEW => 1
            ])
        ]);

        $this->assertFalse($this->caseManagementHelper->requiresManualReview($additionalData));
    }
}
