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

use Adyen\Payment\Gateway\Request\PosCloudBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class PosCloudBuilderTest extends AbstractAdyenTestCase
{
    /**
     * @return void
     */
    public function testBuildAddsClientConfigAndBody()
    {
        $storeId = 1;
        $terminalId = 'P400Plus-123456789';

        $amountCurrencyMock = $this->createConfiguredMock(AdyenAmountCurrency::class, [
            'getCurrencyCode' => 'EUR',
            'getAmount' => 1000
        ]);

        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $chargedCurrencyMock->method('getOrderAmountCurrency')->willReturn($amountCurrencyMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getIncrementId')->willReturn('100000001');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getAdditionalInformation')->willReturnMap([
            ['terminal_id', $terminalId],
            ['funding_source', null],
            ['number_of_installments', null],
        ]);

        $expectedBody = ['SaleToPOIRequest' => ['foo' => 'bar']];
        $pointOfSaleMock = $this->createMock(PointOfSale::class);
        $pointOfSaleMock->expects($this->once())
            ->method('addSaleToAcquirerData')
            ->with($this->isArray(), $orderMock)
            ->willReturn($expectedBody);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $builder = new PosCloudBuilder($chargedCurrencyMock, $pointOfSaleMock);
        $result = $builder->build($buildSubject);

        $this->assertEquals(['storeId' => $storeId], $result['clientConfig']);
        $this->assertEquals($expectedBody, $result['body']);
    }
}
