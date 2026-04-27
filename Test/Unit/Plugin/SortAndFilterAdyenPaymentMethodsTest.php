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

use Adyen\Payment\Helper\PaymentMethodsFilter;
use Adyen\Payment\Plugin\SortAndFilterAdyenPaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use PHPUnit\Framework\MockObject\MockObject;

class SortAndFilterAdyenPaymentMethodsTest extends AbstractAdyenTestCase
{
    private SortAndFilterAdyenPaymentMethods $plugin;
    private PaymentMethodsFilter|MockObject $paymentMethodsFilterMock;
    private CartRepositoryInterface|MockObject $quoteRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodsFilterMock = $this->createMock(PaymentMethodsFilter::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);

        $this->plugin = new SortAndFilterAdyenPaymentMethods(
            $this->paymentMethodsFilterMock,
            $this->quoteRepositoryMock
        );
    }

    public function testAfterGetList(): void
    {
        $cartId = 123;
        $filteredList = [['code' => 'checkmo'], ['code' => 'adyen_cc']];

        $quoteMock = $this->createMock(CartInterface::class);

        $this->quoteRepositoryMock->expects($this->once())
            ->method('get')
            ->with($cartId)
            ->willReturn($quoteMock);

        $this->paymentMethodsFilterMock->expects($this->once())
            ->method('sortAndFilterPaymentMethods')
            ->willReturn([$filteredList, ['ignored' => true]]);

        $subjectMock = $this->createMock(PaymentMethodManagementInterface::class);

        $result = $this->plugin->afterGetList($subjectMock, $filteredList, $cartId);

        $this->assertSame($filteredList, $result);
    }
}
