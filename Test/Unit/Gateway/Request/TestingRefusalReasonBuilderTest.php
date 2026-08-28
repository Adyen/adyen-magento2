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

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Enum\AdyenRefusalReason;
use Adyen\Payment\Gateway\Request\TestingRefusalReasonBuilder;
use Adyen\Payment\Model\TestingRefusalReason;

/**
 * Class TestingRefusalReasonBuilderTest
 *
 * @package Adyen\Payment\Test\Unit\Gateway\Request
 * @coversDefaultClass \Adyen\Payment\Gateway\Request\TestingRefusalReasonBuilder
 */
final class TestingRefusalReasonBuilderTest extends AbstractAdyenTestCase
{
    private Config $configMock;
    private TestingRefusalReason $testingRefusalReasonMock;
    private TestingRefusalReasonBuilder $builder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->testingRefusalReasonMock = $this->createMock(TestingRefusalReason::class);

        $this->builder = new TestingRefusalReasonBuilder(
            $this->configMock,
            $this->testingRefusalReasonMock
        );
    }

    /**
     * @return void
     * @covers ::build()
     */
    public function testBuildReturnsEmptyArrayWhenNotInDemoMode(): void
    {
        $storeId = 1;
        [$paymentDO] = $this->createPaymentSubject($storeId);

        $this->configMock
            ->expects($this->once())
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(false);

        $this->testingRefusalReasonMock
            ->expects($this->never())
            ->method('findRefusalReason');

        $result = $this->builder->build(['payment' => $paymentDO]);
        $this->assertSame([], $result);
    }

    /**
     * @return void
     * @covers ::build()
     */
    public function testBuildReturnsEmptyArrayWhenNoReasonFound(): void
    {
        $storeId = 1;
        [$paymentDO, $order] = $this->createPaymentSubject($storeId);

        $this->configMock
            ->expects($this->once())
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(true);

        $this->testingRefusalReasonMock
            ->expects($this->once())
            ->method('findRefusalReason')
            ->with($order)
            ->willReturn(null);

        $result = $this->builder->build(['payment' => $paymentDO]);
        $this->assertSame([], $result);
    }

    /**
     * @return void
     * @covers ::build()
     */
    public function testBuildReturnsAdditionalDataWhenReasonFound(): void
    {
        $storeId = 1;
        [$paymentDO, $order] = $this->createPaymentSubject($storeId);

        $this->configMock
            ->expects($this->once())
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(true);

        // Use the first available enum case to avoid hardcoding a specific case name.
        $reason = AdyenRefusalReason::cases()[0];

        $this->testingRefusalReasonMock
            ->expects($this->once())
            ->method('findRefusalReason')
            ->with($order)
            ->willReturn($reason);

        $result = $this->builder->build(['payment' => $paymentDO]);

        $expected = [
            'body' => [
                'additionalData' => [
                    'RequestedTestAcquirerResponseCode' => $reason->value,
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * @return array{0: PaymentDataObjectInterface, 1: Order}
     */
    private function createPaymentSubject(int $storeId): array
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId'])
            ->getMock();
        $order->method('getStoreId')->willReturn($storeId);

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $payment->method('getOrder')->willReturn($order);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return [$paymentDO, $order];
    }
}
