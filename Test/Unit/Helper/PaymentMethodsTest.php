<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Helper\PaymentMethods;
use PHPUnit\Framework\TestCase;

class PaymentMethodsTest extends TestCase
{
    /**
     * @var PaymentMethods
     */
    private $paymentMethodsHelper;

    protected function setUp(): void
    {
        $this->paymentMethodsHelper = $this->getMockBuilder(PaymentMethods::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return void
     */
    public function testIsAdyenPaymentTrue()
    {
        $paymentMethodCode = 'adyen_cc';

        $this->assertEquals(
            true,
            $this->paymentMethodsHelper->isAdyenPayment($paymentMethodCode)
        );
    }

    /**
     * @param $paymentMethodCode
     * @return void
     */
    public function testIsAdyenPaymentFalse()
    {
        $paymentMethodCode = 'different_payment_method';

        $this->assertEquals(
            false,
            $this->paymentMethodsHelper->isAdyenPayment($paymentMethodCode)
        );
    }
}