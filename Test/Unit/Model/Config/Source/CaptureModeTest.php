<?php

namespace Adyen\Payment\Test\Unit\Model\Config\Source;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Config\Source\CaptureMode;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CaptureModeTest extends AbstractAdyenTestCase
{
    protected ?CaptureMode $captureMode;
    protected Data|MockObject $adyenHelperMock;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createPartialMock(Data::class, []);
        $this->captureMode = new CaptureMode($this->adyenHelperMock);
    }

    protected function tearDown(): void
    {
        $this->captureMode = null;
    }

    public function testToOptionArray()
    {
        $expected = [
            [
                'value' => CaptureMode::CAPTURE_MODE_AUTO,
                'label' => CAPTUREMode::CAPTURE_MODE_AUTO_LABEL
            ],
            [
                'value' => CaptureMode::CAPTURE_MODE_MANUAL,
                'label' => CAPTUREMode::CAPTURE_MODE_MANUAL_LABEL
            ]
        ];

        $this->assertEquals($expected, $this->captureMode->toOptionArray());
    }
}
