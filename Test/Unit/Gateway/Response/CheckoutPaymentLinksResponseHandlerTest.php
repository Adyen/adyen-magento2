<?php

namespace Test\Unit\Gateway\Response;

use Adyen\Payment\Gateway\Response\CheckoutPaymentLinksResponseHandler;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class CheckoutPaymentLinksResponseHandlerTest extends AbstractAdyenTestCase
{
    protected ?CheckoutPaymentLinksResponseHandler $handler;
    protected Data|MockObject $adyenHelperMock;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);

        $this->handler = new CheckoutPaymentLinksResponseHandler(
            $this->adyenHelperMock
        );
    }

    protected function tearDown(): void
    {
        $this->handler = null;
    }

    public function testHandleSuccess(): void
    {
        $responseMock = [
            'url' => 'https://www.example.com/paymentlink/1234',
            'expiresAt' => '2025-01-01 00:00:00',
            'id' => 'XY123456'
        ];

        $paymentMock = $this->createMock(Payment::class);

        $paymentMock->expects($this->once())->method('setIsTransactionPending')->with(true);
        $paymentMock->expects($this->once())->method('setIsTransactionClosed')->with(false);
        $paymentMock->expects($this->once())->method('setShouldCloseParentTransaction')
            ->with(false);

        $infoInstance = $this->createMock(PaymentDataObjectInterface::class);
        $infoInstance->method('getPayment')->willReturn($paymentMock);

        $handlingSubject = ['payment' => $infoInstance];

        $this->handler->handle($handlingSubject, $responseMock);
    }
}
