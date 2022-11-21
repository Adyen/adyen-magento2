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
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use PHPUnit\Framework\TestCase;

class CaseManagementTest extends AbstractAdyenTestCase
{
    /**
     * @return bool
     */
    public function testRequiresManualReviewTrue()
    {
        $additionalData = [CaseManagement::FRAUD_MANUAL_REVIEW => 'true'];
        $caseManagementHelper = $this->createCaseManagementHelper();

        $this->assertTrue($caseManagementHelper->requiresManualReview($additionalData));
    }

    /**
     * @return bool
     */
    public function testRequiresManualReviewNoFraudKey()
    {
        $additionalData = ['test' => 'myPatience'];
        $caseManagementHelper = $this->createCaseManagementHelper();

        $this->assertFalse($caseManagementHelper->requiresManualReview($additionalData));
    }

    /**
     * @return bool
     */
    public function testRequiresManualReviewUnexpectedValue()
    {
        $additionalData = [CaseManagement::FRAUD_MANUAL_REVIEW => '1'];
        $caseManagementHelper = $this->createCaseManagementHelper();

        $this->assertFalse($caseManagementHelper->requiresManualReview($additionalData));
    }

    /**
     * @param $adyenLoggerMock
     * @param $adyenConfigHelperMock
     * @return CaseManagement
     */
    public function createCaseManagementHelper(
        $adyenLoggerMock = null,
        $adyenConfigHelperMock = null
    ): CaseManagement
    {
        if (is_null($adyenLoggerMock)) {
            $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        }

        if (is_null($adyenConfigHelperMock)) {
            $adyenConfigHelperMock = $this->createMock(Config::class);
        }

        return new CaseManagement(
            $adyenLoggerMock,
            $adyenConfigHelperMock
        );
    }
}
