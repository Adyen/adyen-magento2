<?php

namespace Adyen\Payment\Test\Unit\Model\Config\Source;

use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class ThreeDSModeTest extends AbstractAdyenTestCase
{
    public function testToOptionArray()
    {
        $threeDSModeSource = new ThreeDSFlow();

        $expected = [
            ['value' => ThreeDSFlow::THREEDS_NATIVE, 'label' => __('Native 3D Secure 2')],
            ['value' => ThreeDSFlow::THREEDS_REDIRECT, 'label' => __('Redirect 3D Secure 2')],
        ];

        $this->assertEquals($expected, $threeDSModeSource->toOptionArray());
    }
}
