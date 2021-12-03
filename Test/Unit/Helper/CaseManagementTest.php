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

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment as AdyenPaymentModel;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class CaseManagementTest extends TestCase
{
    /**
     * @var CaseManagement
     */
    private $caseManagementHelper;
    /**
     * @var Payment|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockOrderPaymentResourceModel;
    /**
     * @var Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockAdyenDataHelper;
    /**
     * @var PaymentFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockAdyenOrderPaymentFactory;

    public function setUp(): void
    {
        $this->caseManagementHelper = new CaseManagement(
            $this->getSimpleMock(Context::class),
            $this->getSimpleMock(AdyenLogger::class),
            $this->getSimpleMock(Config::class)
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


    /**
     * TODO: Abstract this in a parent
     * @param $originalClassName
     * @return mixed
     */
    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
