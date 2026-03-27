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

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Block\Adminhtml\System\Config\Field;

use Adyen\Payment\Block\Adminhtml\System\Config\Field\PaymentMethodTitles;
use Adyen\Payment\Block\Adminhtml\System\Config\Field\PaymentMethodType;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DataObject;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(PaymentMethodTitles::class)]
class PaymentMethodTitlesTest extends AbstractAdyenTestCase
{
    private PaymentMethodTitles $block;
    private MockObject $layoutMock;
    private MockObject $rendererMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rendererMock = $this->getMockBuilder(PaymentMethodType::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['calcOptionHash'])
            ->getMock();

        $this->layoutMock = $this->createMock(LayoutInterface::class);
        $this->layoutMock->method('createBlock')
            ->with(PaymentMethodType::class, '', ['data' => ['is_render_to_js_template' => true]])
            ->willReturn($this->rendererMock);

        $this->block = $this->getMockBuilder(PaymentMethodTitles::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLayout'])
            ->getMock();

        $this->block->method('getLayout')->willReturn($this->layoutMock);
    }

    public function testPrepareArrayRowSetsSelectedOption(): void
    {
        $this->rendererMock->method('calcOptionHash')
            ->with('scheme')
            ->willReturn('123456');

        $row = new DataObject([
            'payment_method_type' => 'scheme',
            'title' => 'Kreditkarte',
        ]);

        $this->invokeMethod($this->block, '_prepareArrayRow', [$row]);

        $expected = ['option_123456' => 'selected="selected"'];
        $this->assertSame($expected, $row->getData('option_extra_attrs'));
    }

    public function testPrepareArrayRowWithEmptyTypeSetsNoOptions(): void
    {
        $row = new DataObject([
            'payment_method_type' => '',
            'title' => 'Some Title',
        ]);

        $this->invokeMethod($this->block, '_prepareArrayRow', [$row]);

        $this->assertSame([], $row->getData('option_extra_attrs'));
    }

    public function testPrepareArrayRowWithNullTypeSetsNoOptions(): void
    {
        $row = new DataObject([
            'title' => 'Some Title',
        ]);

        $this->invokeMethod($this->block, '_prepareArrayRow', [$row]);

        $this->assertSame([], $row->getData('option_extra_attrs'));
    }

    public function testPrepareToRenderAddsColumnsAndSetsButtonLabel(): void
    {
        $this->invokeMethod($this->block, '_prepareToRender');

        $reflection = new \ReflectionClass($this->block);

        $columnsProperty = $reflection->getProperty('_columns');
        $columnsProperty->setAccessible(true);
        $columns = $columnsProperty->getValue($this->block);

        $this->assertArrayHasKey('payment_method_type', $columns);
        $this->assertArrayHasKey('title', $columns);

        $addAfterProperty = $reflection->getProperty('_addAfter');
        $addAfterProperty->setAccessible(true);
        $this->assertFalse($addAfterProperty->getValue($this->block));

        $addButtonLabelProperty = $reflection->getProperty('_addButtonLabel');
        $addButtonLabelProperty->setAccessible(true);
        $this->assertEquals('Add Override', (string) $addButtonLabelProperty->getValue($this->block));
    }

    public function testGetPaymentMethodTypeRendererReturnsSameInstance(): void
    {
        $this->layoutMock->expects($this->once())
            ->method('createBlock')
            ->willReturn($this->rendererMock);

        $renderer1 = $this->invokeMethod($this->block, 'getPaymentMethodTypeRenderer');
        $renderer2 = $this->invokeMethod($this->block, 'getPaymentMethodTypeRenderer');

        $this->assertSame($renderer1, $renderer2);
    }
}
