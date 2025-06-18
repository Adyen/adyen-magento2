<?php

namespace Test\Unit\Gateway\Response;

use Adyen\Payment\Gateway\Response\ModificationsCapturesResponseHandler;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class ModificationsCapturesResponseHandlerTest extends AbstractAdyenTestCase
{
    protected ?ModificationsCapturesResponseHandler $modificationsCapturesResponseHandler;
    protected Invoice|MockObject $invoiceMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;

    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->invoiceMock = $this->createMock(Invoice::class);

        $this->modificationsCapturesResponseHandler = new ModificationsCapturesResponseHandler(
            $this->adyenLoggerMock,
            $this->invoiceMock,
        );
    }

    protected function tearDown(): void
    {
        $this->modificationsCapturesResponseHandler = null;
    }

    public function testHandle(): void
    {
        $orderMock = $this->createMock(Order::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->any())->method('getOrder')->willReturn($orderMock);

        $paymentMock->expects($this->exactly(2))->method('setLastTransId');
        $paymentMock->expects($this->once())->method('setIsTransactionPending')->with(true);
        $paymentMock->expects($this->once())->method('setShouldCloseParentTransaction')->with(false);

        $infoInstance = $this->createMock(PaymentDataObjectInterface::class);
        $infoInstance->method('getPayment')->willReturn($paymentMock);

        $handlingSubject = ['payment' => $infoInstance];

        $responseCollection = [
            [
                'status' => 'received',
                'pspReference' => 'XYZ123456',
                'paymentPspReference' => 'XYZ123456',
                'amount' => [
                    'value' => 1000,
                ]
            ],
            [
                'status' => 'received',
                'pspReference' => 'ABC123456',
                'paymentPspReference' => 'ABC123456',
                'amount' => [
                    'value' => 1000,
                ]
            ]
        ];

        $this->adyenLoggerMock->expects($this->exactly(2))->method('addAdyenInfoLog');
        $this->invoiceMock->expects($this->exactly(2))->method('createAdyenInvoice');

        $this->modificationsCapturesResponseHandler->handle($handlingSubject, $responseCollection);
    }
}
