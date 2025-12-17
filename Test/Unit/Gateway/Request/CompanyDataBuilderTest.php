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

use Adyen\Payment\Gateway\Request\CompanyDataBuilder;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class CompanyDataBuilderTest extends AbstractAdyenTestCase
{
    /**
     * @return void
     */
    function testBuild()
    {
        $companyName = 'Adyen';
        $vatId = 'NL-123456789';

        $billingAddressMock = $this->createMock(OrderAddressInterface::class);
        $billingAddressMock->expects($this->exactly(2))
            ->method('getCompany')
            ->willReturn('Adyen');
        $billingAddressMock->expects($this->exactly(2))
            ->method('getVatId')
            ->willReturn($vatId);

        $orderMock = $this->createMock(Order::class);
        $orderMock->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($billingAddressMock);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $builder = new CompanyDataBuilder();
        $result = $builder->build($buildSubject);

        $this->assertEquals(['body' => ['company' => ['name' => $companyName, 'taxId' => $vatId]]], $result);
    }
}
