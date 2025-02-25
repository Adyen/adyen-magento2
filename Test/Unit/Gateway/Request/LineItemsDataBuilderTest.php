<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\LineItemsDataBuilder;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class LineItemsDataBuilderTest extends AbstractAdyenTestCase
{
    protected ?LineItemsDataBuilder $lineItemsDataBuilder;
    protected PaymentMethods|MockObject $paymentMethodsHelperMock;
    protected OpenInvoice|MockObject $openInvoiceHelperMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->openInvoiceHelperMock = $this->createMock(OpenInvoice::class);

        $this->lineItemsDataBuilder = new LineItemsDataBuilder(
            $this->paymentMethodsHelperMock,
            $this->openInvoiceHelperMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->lineItemsDataBuilder = null;
    }

    /**
     * @return array
     */
    private static function buildDataProvider(): array
    {
        return [
            ['isLineItemsRequired' => true],
            ['isLineItemsRequired' => false]
        ];
    }

    /**
     * @dataProvider buildDataProvider()
     *
     * @param bool $isLineItemsRequired
     * @return void
     * @throws LocalizedException
     */
    public function testBuild(bool $isLineItemsRequired)
    {
        $orderMock = $this->createMock(Order::class);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createMock(Order\Payment::class);
        $paymentMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);

        $paymentDataObjectMock = $this->createMock(PaymentDataObject::class);
        $paymentDataObjectMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $this->paymentMethodsHelperMock->expects($this->once())
            ->method('getRequiresLineItems')
            ->willReturn($isLineItemsRequired);

        if ($isLineItemsRequired) {
            $this->openInvoiceHelperMock->expects($this->once())
                ->method('getOpenInvoiceDataForOrder')
                ->with($orderMock)
                ->willReturn(['lineItems' => [['id' => 1], ['id' => 2]]]);
        }

        $buildSubject = ['payment' => $paymentDataObjectMock];
        $result = $this->lineItemsDataBuilder->build($buildSubject);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);

        if ($isLineItemsRequired) {
            $this->assertArrayHasKey('lineItems', $result['body']);
            $this->assertArrayHasKey('id', $result['body']['lineItems'][0]);
        } else {
            $this->assertEmpty($result['body']);
        }
    }
}
