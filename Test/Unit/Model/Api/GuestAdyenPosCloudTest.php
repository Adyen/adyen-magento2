<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Adyen\Payment\Model\Api\GuestAdyenPosCloud;

class GuestAdyenPosCloudTest extends AbstractAdyenTestCase
{
    private CommandPoolInterface $commandPoolMock;
    protected AdyenLogger $adyenLoggerMock;
    protected OrderRepository $orderRepositoryMock;
    protected QuoteIdMaskFactory $quoteIdMaskFactoryMock;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactoryMock;
    protected GuestAdyenPosCloud $guestAdyenPosCloud;

    protected function setUp(): void
    {
        $this->commandPoolMock = $this->createMock(CommandPoolInterface::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->paymentDataObjectFactoryMock = $this->createMock(PaymentDataObjectFactoryInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, ['create']);
        $this->guestAdyenPosCloud = $this->getMockBuilder(GuestAdyenPosCloud::class)
            ->setConstructorArgs([
                $this->commandPoolMock,
                $this->orderRepositoryMock,
                $this->paymentDataObjectFactoryMock,
                $this->adyenLoggerMock,
                $this->quoteIdMaskFactoryMock
            ])
            ->onlyMethods(['execute'])
            ->getMock();
    }

    public function testPayByCartSuccessfully()
    {
        $catId = '123';
        $quoteId = 11;
        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn($quoteId);
        $this->quoteIdMaskFactoryMock->method('create')->willReturn($quoteIdMaskMock);
        $orderMock = $this->createMock(OrderInterface::class);
        $this->orderRepositoryMock->expects($this->once())->method('getOrderByQuoteId')->with($quoteId)->willReturn($orderMock);
        $this->guestAdyenPosCloud->payByCart($catId);
    }
}
