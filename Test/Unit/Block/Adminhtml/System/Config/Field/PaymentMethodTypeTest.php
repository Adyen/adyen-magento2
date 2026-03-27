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

use Adyen\Payment\Block\Adminhtml\System\Config\Field\PaymentMethodType;
use Adyen\Payment\Model\Config\Source\PaymentMethodType as PaymentMethodTypeSource;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(PaymentMethodType::class)]
class PaymentMethodTypeTest extends AbstractAdyenTestCase
{
    private MockObject $block;
    private MockObject $sourceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceMock = $this->createMock(PaymentMethodTypeSource::class);

        $escaperMock = $this->createMock(Escaper::class);
        $escaperMock->method('escapeHtml')->willReturnArgument(0);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getEscaper')->willReturn($escaperMock);

        $this->block = $this->getMockBuilder(PaymentMethodType::class)
            ->setConstructorArgs([$contextMock, $this->sourceMock])
            ->onlyMethods([])
            ->getMock();
    }

    public function testSetInputNameDelegatesToSetName(): void
    {
        $result = $this->block->setInputName('test_name');

        $this->assertInstanceOf(PaymentMethodType::class, $result);
        $this->assertSame('test_name', $this->block->getName());
    }

    public function testSetInputIdDelegatesToSetId(): void
    {
        $result = $this->block->setInputId('test_id');

        $this->assertInstanceOf(PaymentMethodType::class, $result);
        $this->assertSame('test_id', $this->block->getId());
    }

    public function testToHtmlPopulatesOptionsFromSource(): void
    {
        $this->sourceMock->expects($this->once())
            ->method('toOptionArray')
            ->willReturn([
                ['value' => 'scheme', 'label' => 'Cards (scheme)'],
                ['value' => 'ideal', 'label' => 'iDEAL (ideal)'],
            ]);

        $this->assertEmpty($this->block->getOptions());

        $this->invokeMethod($this->block, '_toHtml');

        $options = $this->block->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame('scheme', $options[0]['value']);
        $this->assertSame('Cards (scheme)', $options[0]['label']);
        $this->assertSame('ideal', $options[1]['value']);
        $this->assertSame('iDEAL (ideal)', $options[1]['label']);
    }

    public function testToHtmlDoesNotReloadOptionsWhenAlreadySet(): void
    {
        $this->sourceMock->expects($this->once())
            ->method('toOptionArray')
            ->willReturn([
                ['value' => 'scheme', 'label' => 'Cards (scheme)'],
            ]);

        $this->invokeMethod($this->block, '_toHtml');
        $this->invokeMethod($this->block, '_toHtml');

        $this->assertCount(1, $this->block->getOptions());
    }
}
