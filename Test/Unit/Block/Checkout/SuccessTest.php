<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Block\Checkout;

use Adyen\Payment\Block\Checkout\Success;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteIdToMaskedQuoteId;

class SuccessTest extends AbstractAdyenTestCase
{
    private $objectManager;
    private $checkoutSessionMock;
    private $customerSessionMock;
    private $orderFactoryMock;
    private $orderMock;
    private $quoteIdToMaskedQuoteIdMock;
    private $successBlock;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->checkoutSessionMock = $this->createGeneratedMock(
            CheckoutSession::class,
            ['getLastOrderId']
        );
        $this->customerSessionMock = $this->createGeneratedMock(
            CustomerSession::class,
            ['isLoggedIn']
        );
        $this->orderFactoryMock = $this->createGeneratedMock(OrderFactory::class, ['create']);
        $this->orderMock = $this->createMock(Order::class);
        $this->quoteIdToMaskedQuoteIdMock = $this->createMock(QuoteIdToMaskedQuoteId::class);

        $this->successBlock = $this->objectManager->getObject(
            Success::class,
            [
                'checkoutSession' => $this->checkoutSessionMock,
                'customerSession' => $this->customerSessionMock,
                'orderFactory' => $this->orderFactoryMock,
                'quoteIdToMaskedQuoteId' => $this->quoteIdToMaskedQuoteIdMock
            ]
        );
    }

    public function testGetMaskedQuoteIdSuccessful()
    {
        $orderId = 100;
        $maskedQuoteId = 'masked_id_123';
        $quoteId = 1;

        $this->checkoutSessionMock->method('getLastOrderId')->willReturn($orderId);
        $this->orderFactoryMock->method('create')->willReturn($this->orderMock);
        $this->orderMock->method('load')->willReturnSelf();
        $this->orderMock->method('getQuoteId')->willReturn($quoteId);
        $this->quoteIdToMaskedQuoteIdMock->method('execute')->willReturn($maskedQuoteId);

        $result = $this->successBlock->getMaskedQuoteId();
        $this->assertEquals($maskedQuoteId, $result);
    }

    public function testGetMaskedQuoteIdException()
    {
        $orderId = 100;
        $exception = new \Exception('Error during getMaskedQuoteId');

        $this->checkoutSessionMock->method('getLastOrderId')->willReturn($orderId);
        $this->orderFactoryMock->method('create')->willThrowException($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error during getMaskedQuoteId');

        $this->successBlock->getMaskedQuoteId();
    }

    public function testGetIsCustomerLoggedIn()
    {
        $this->customerSessionMock->method('isLoggedIn')->willReturn(true);

        $this->assertTrue($this->successBlock->getIsCustomerLoggedIn());
    }
}
