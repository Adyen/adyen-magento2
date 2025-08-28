<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model;

use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use Adyen\Payment\Enum\AdyenRefusalReason;
use Adyen\Payment\Enum\CallbackOrderProperty;
use Adyen\Payment\Helper\Config\Testing;
use Adyen\Payment\Model\TestingRefusalReason;

/**
 * Class TestingRefusalReasonTest
 *
 * @package Adyen\Payment\Test\Unit\Model
 * @coversDefaultClass \Adyen\Payment\Model\TestingRefusalReason
 */
class TestingRefusalReasonTest extends AbstractAdyeNTestCase
{
    private Testing $testingConfigMock;
    private TestingRefusalReason $subject;
    private int $storeId = 1;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->testingConfigMock = $this->createMock(Testing::class);
        $this->subject = new TestingRefusalReason($this->testingConfigMock);
    }

    /**
     * @param DataObject|null $shippingAddress
     * @return Order
     */
    private function createOrderWithShippingAddress(?DataObject $shippingAddress): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn($this->storeId);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        return $order;
    }

    /**
     * @return void
     * @covers ::findRefusalReason()
     */
    public function testReturnsMappedEnumWhenShippingValueMatches(): void
    {
        $source = CallbackOrderProperty::ShippingLastName;

        $parts = explode('.', $source->value);
        $this->assertCount(2, $parts, 'Expected a two-part source like "shipping.key"');
        $key = $parts[1];

        $inputValue = 'Smith';
        $expectedReason = AdyenRefusalReason::cases()[0];

        $this->testingConfigMock
            ->method('getRefusalReasonValueSource')
            ->with($this->storeId)
            ->willReturn($source);

        $this->testingConfigMock
            ->method('getRefusalReasonMapping')
            ->with($this->storeId)
            ->willReturn([$inputValue => $expectedReason]);

        $shipping = new DataObject([$key => $inputValue]);
        $order = $this->createOrderWithShippingAddress($shipping);

        $actual = $this->subject->findRefusalReason($order);

        $this->assertSame($expectedReason, $actual);
    }

    /**
     * @return void
     * @covers ::findRefusalReason()
     */
    public function testReturnsNullWhenValueIsMissing(): void
    {
        $source = CallbackOrderProperty::ShippingLastName;

        $parts = explode('.', $source->value);
        $this->assertCount(2, $parts, 'Expected a two-part source like "shipping.key"');

        $this->testingConfigMock
            ->method('getRefusalReasonValueSource')
            ->with($this->storeId)
            ->willReturn($source);

        $this->testingConfigMock
            ->expects($this->never())
            ->method('getRefusalReasonMapping')
            ->with($this->storeId);

        $shipping = new DataObject(); // No key set => null/empty
        $order = $this->createOrderWithShippingAddress($shipping);

        $this->assertNull($this->subject->findRefusalReason($order));
    }

    /**
     * @return void
     * @covers ::findRefusalReason()
     */
    public function testReturnsNullWhenMappingHasNoMatch(): void
    {
        $source = CallbackOrderProperty::ShippingLastName;

        $parts = explode('.', $source->value);
        $this->assertCount(2, $parts, 'Expected a two-part source like "shipping.key"');
        $key = $parts[1];

        $this->testingConfigMock
            ->method('getRefusalReasonValueSource')
            ->with($this->storeId)
            ->willReturn($source);

        $this->testingConfigMock
            ->method('getRefusalReasonMapping')
            ->with($this->storeId)
            ->willReturn(['MappedValue' => AdyenRefusalReason::cases()[0]]);

        $shipping = new DataObject([$key => 'UnmappedValue']);
        $order = $this->createOrderWithShippingAddress($shipping);

        $this->assertNull($this->subject->findRefusalReason($order));
    }

    /**
     * @return void
     * @covers ::findRefusalReason()
     */
    public function testReturnsNullWhenShippingAddressIsNull(): void
    {
        $source = CallbackOrderProperty::ShippingLastName;

        $this->testingConfigMock
            ->method('getRefusalReasonValueSource')
            ->with($this->storeId)
            ->willReturn($source);

        $this->testingConfigMock
            ->expects($this->never())
            ->method('getRefusalReasonMapping')
            ->with($this->storeId);

        $order = $this->createOrderWithShippingAddress(null);

        $this->assertNull($this->subject->findRefusalReason($order));
    }
}
