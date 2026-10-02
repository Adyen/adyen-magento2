<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model\Config\Source;

use Adyen\Payment\Model\Config\Source\TerminalApiRegion;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Region;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TerminalApiRegionTest extends AbstractAdyenTestCase
{
    private TerminalApiRegion $terminalApiRegionSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->terminalApiRegionSource = new TerminalApiRegion();
    }

    public function testToOptionArray()
    {
        $expected = [
            ['value' => Region::EU, 'label' => __('Default (EU - Europe)')],
            ['value' => Region::US, 'label' => __('US - United States')],
            ['value' => Region::AU, 'label' => __('AU - Australia')],
            ['value' => Region::APSE, 'label' => __('APSE - Asia Pacific Southeast')]
        ];

        $this->assertEquals($expected, $this->terminalApiRegionSource->toOptionArray());
    }

    public function testToOptionArrayReturnsAllSupportedRegionValues()
    {
        $values = array_column($this->terminalApiRegionSource->toOptionArray(), 'value');

        $this->assertSame(
            [Region::EU, Region::US, Region::AU, Region::APSE],
            $values
        );
    }

    public function testToOptionArrayIncludesApseRegion()
    {
        $values = array_column($this->terminalApiRegionSource->toOptionArray(), 'value');

        $this->assertContains(Region::APSE, $values);
    }
}
