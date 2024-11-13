<?php

namespace Adyen\Payment\Test\Unit\Model\Config\Source;

use Adyen\Payment\Model\Config\Source\ThreeDSMode;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class ThreeDSModeTest extends AbstractAdyenTestCase
{
    public function testToOptionArray()
    {
        $threeDSModeSource = new ThreeDSMode();

        $expected = [
            ['value' => ThreeDSMode::THREEDS_MODE_NATIVE, 'label' => __('Native 3D Secure 2')],
            ['value' => ThreeDSMode::THREEDS_MODE_REDIRECT, 'label' => __('Redirect 3D Secure 2')],
        ];

        $this->assertEquals($expected, $threeDSModeSource->toOptionArray());
    }
}
