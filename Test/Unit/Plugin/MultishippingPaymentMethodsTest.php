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

namespace Adyen\Payment\Test\Plugin;

use Adyen\Payment\Block\Checkout\Multishipping\Billing;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFilter;
use Adyen\Payment\Plugin\MultishippingPaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;

class MultishippingPaymentMethodsTest extends AbstractAdyenTestCase
{
    protected ?MultishippingPaymentMethods $multishippingPaymentMethods;
    protected PaymentMethodsFilter|MockObject $paymentMethodsFilterMock;
    protected Config|MockObject $configHelperMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodsFilterMock = $this->createMock(PaymentMethodsFilter::class);
        $this->configHelperMock = $this->createMock(Config::class);

        $this->multishippingPaymentMethods = new MultishippingPaymentMethods(
            $this->paymentMethodsFilterMock,
            $this->configHelperMock
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->multishippingPaymentMethods = null;
    }

    public function testAfterGetMethodsAdyenMethodsInactive(): void
    {
        $storeId = 1;
        $methods = [['code' => 'checkmo']];

        $quoteMock = $this->createMock(Quote::class);
        $quoteMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn($storeId);

        $billingMock = $this->createMock(Billing::class);
        $billingMock->expects($this->once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentMethodsActive')
            ->with($storeId)
            ->willReturn(false);

        $this->paymentMethodsFilterMock->expects($this->never())
            ->method('sortAndFilterPaymentMethods');
        $billingMock->expects($this->never())
            ->method('setAdyenPaymentMethodsResponse');

        $result = $this->multishippingPaymentMethods->afterGetMethods($billingMock, $methods);

        $this->assertSame($methods, $result);
    }

    public function testAfterGetMethodsWithAdyenMethods(): void
    {
        $storeId = 1;
        $filteredMethods = [['code' => 'checkmo'], ['code' => 'adyen_cc']];
        $apiResponse = "{'paymentMethods': 'mock_result'}";

        $quoteMock = $this->createMock(Quote::class);
        $quoteMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn($storeId);

        $billingMock = $this->createMock(Billing::class);
        $billingMock->expects($this->once())
            ->method('getQuote')
            ->willReturn($quoteMock);

        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentMethodsActive')
            ->with($storeId)
            ->willReturn(true);

        $this->paymentMethodsFilterMock->expects($this->once())
            ->method('sortAndFilterPaymentMethods')
            ->willReturn([$filteredMethods, $apiResponse]);

        $billingMock->expects($this->once())
            ->method('setAdyenPaymentMethodsResponse')
            ->with($apiResponse);

        $result = $this->multishippingPaymentMethods->afterGetMethods($billingMock, $filteredMethods);

        $this->assertSame($filteredMethods, $result);
    }
}
