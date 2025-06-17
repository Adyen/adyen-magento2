<?php

namespace Test\Unit\Gateway\Response;

use Adyen\Payment\Gateway\Response\ModificationsRefundsResponseHandler;
use Adyen\Payment\Helper\Creditmemo;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class ModificationsRefundsResponseHandlerTest extends AbstractAdyenTestCase
{
    protected ?ModificationsRefundsResponseHandler $modificationsRefundsResponseHandler;
    protected Creditmemo|MockObject $creditmemoHelperMock;
    protected Data|MockObject $adyenHelperMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;

    protected function setUp(): void
    {
        $this->creditmemoHelperMock = $this->createMock(Creditmemo::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->modificationsRefundsResponseHandler = new ModificationsRefundsResponseHandler(
            $this->creditmemoHelperMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->modificationsRefundsResponseHandler = null;
    }

    public function testHandle(): void
    {
        $invoiceMock = $this->createMock(Invoice::class);

        $creditMemoMock = $this->createMock(Order\Creditmemo::class);
        $creditMemoMock->expects($this->once())->method('getInvoice')->willReturn($invoiceMock);

        $orderMock = $this->createMock(Order::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->any())->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->once())->method('getCreditmemo')->willReturn($creditMemoMock);
        $paymentMock->expects($this->exactly(2))->method('setLastTransId');
        $paymentMock->expects($this->once())->method('setIsTransactionClosed')->with(true);
        $paymentMock->expects($this->once())->method('setShouldCloseParentTransaction');

        $infoInstance = $this->createMock(PaymentDataObjectInterface::class);
        $infoInstance->method('getPayment')->willReturn($paymentMock);

        $handlingSubject = ['payment' => $infoInstance];

        $responseCollection = [
            [
                'status' => 'received',
                'pspReference' => 'XYZ123456',
                'original_reference' => 'XYZ123456',
                'refund_amount' => 1000,
                'refund_currency' => 'EUR',
            ],
            [
                'status' => 'received',
                'pspReference' => 'ABC123456',
                'original_reference' => 'ABC123456',
                'refund_amount' => 5000,
                'refund_currency' => 'EUR',
            ]
        ];

        $this->adyenHelperMock->expects($this->exactly(2))->method('originalAmount')->willReturn(10.00);

        $this->creditmemoHelperMock->expects($this->exactly(2))->method('createAdyenCreditMemo');
        $this->adyenLoggerMock->expects($this->atLeast(1))->method('addAdyenInfoLog');

        $this->modificationsRefundsResponseHandler->handle($handlingSubject, $responseCollection);
    }
}
