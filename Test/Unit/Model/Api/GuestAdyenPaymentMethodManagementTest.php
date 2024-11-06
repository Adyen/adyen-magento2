<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Model\Api\GuestAdyenPaymentMethodManagement;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenPaymentMethodManagementTest extends AbstractAdyenTestCase
{
    /** @var QuoteIdMaskFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $quoteIdMaskFactoryMock;

    /** @var PaymentMethods|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentMethodsHelperMock;

    /** @var GuestAdyenPaymentMethodManagement */
    private $guestAdyenPaymentMethodManagement;

    protected function setUp(): void
    {
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, ['create']);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);

        $this->guestAdyenPaymentMethodManagement = new GuestAdyenPaymentMethodManagement(
            $this->quoteIdMaskFactoryMock,
            $this->paymentMethodsHelperMock
        );
    }

    public function testGetPaymentMethods()
    {
        $cartId = '123';
        $quoteId = 123;
        $shopperLocale = 'en_US';
        $country = 'US';
        $channel = 'Web';
        $expectedPaymentMethods = 'sample_payment_methods';

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn($quoteId);
        $this->quoteIdMaskFactoryMock->method('create')->willReturn($quoteIdMaskMock);


//        $this->quoteIdMaskFactoryMock->expects($this->once())
//            ->method('create')
//            ->willReturn($quoteIdMaskMock);

        $this->paymentMethodsHelperMock->expects($this->once())
            ->method('getPaymentMethods')
            ->with($quoteId, $country, $shopperLocale, $channel)
            ->willReturn($expectedPaymentMethods);

        $result = $this->guestAdyenPaymentMethodManagement->getPaymentMethods($cartId, $shopperLocale, $country, $channel);

        $this->assertEquals($expectedPaymentMethods, $result);
    }
}
