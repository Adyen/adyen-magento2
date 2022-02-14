<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\Context;
use PHPUnit\Framework\TestCase;

class CaseManagementTest extends TestCase
{
    /**
     * @var CaseManagement
     */
    private $caseManagementHelper;

    public function setUp(): void
    {
        $this->caseManagementHelper = new CaseManagement(
            $this->createMock(AdyenLogger::class),
            $this->createMock(Config::class)
        );
    }

    public function testRequiresManualReviewTrue()
    {
        $additionalData = [CaseManagement::FRAUD_MANUAL_REVIEW => 'true'];

        $this->assertTrue($this->caseManagementHelper->requiresManualReview($additionalData));
    }

    public function testRequiresManualReviewNoFraudKey()
    {
        $additionalData = ['test' => 'myPatience'];

        $this->assertFalse($this->caseManagementHelper->requiresManualReview($additionalData));
    }

    public function testRequiresManualReviewUnexpectedValue()
    {
        $additionalData = [CaseManagement::FRAUD_MANUAL_REVIEW => '1'];

        $this->assertFalse($this->caseManagementHelper->requiresManualReview($additionalData));
    }
}
