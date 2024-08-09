<?php

namespace Adyen\Payment\Test\Unit\Model\Config\Source;

use Adyen\Payment\Model\Config\Source\PaymentAction;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Model\MethodInterface;

class PaymentActionTest extends AbstractAdyenTestCase
{
    public function testToOptionArray()
    {
        $paymentActionClass = new PaymentAction();

        $expected = [
            ['value' => MethodInterface::ACTION_AUTHORIZE, 'label' => __('After payment')],
            ['value' => MethodInterface::ACTION_ORDER, 'label' => __('Before payment')],
        ];

        $this->assertEquals($expected, $paymentActionClass->toOptionArray());
    }
}
