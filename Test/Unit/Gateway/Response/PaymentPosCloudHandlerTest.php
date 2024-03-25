<?php

namespace Adyen\Payment\Test\Gateway\Response;

use Adyen\Payment\Gateway\Response\PaymentPosCloudHandler;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\StatusResolver;

class PaymentPosCloudHandlerTest extends AbstractAdyenTestCase
{
    private AdyenLogger $adyenLogger;
    private Vault $vaultHelper;
    private StatusResolver $statusResolver;
    private Quote $quoteHelper;
    private PaymentPosCloudHandler $paymentPosCloudHandler;

    protected function setUp(): void
    {
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->vaultHelper = $this->createMock(Vault::class);
        $this->statusResolver = $this->createMock(StatusResolver::class);
        $this->quoteHelper = $this->createMock(Quote::class);
        $this->paymentPosCloudHandler = new PaymentPosCloudHandler(
            $this->adyenLogger,
            $this->vaultHelper,
            $this->statusResolver,
            $this->quoteHelper
        );
    }

    public function testHandlePosResponse()
    {
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $payment->method('getOrder')->willReturn($order);
        $dataObject = $this->createMock(PaymentDataObjectInterface::class);
        $dataObject->method('getPayment')->willReturn($payment);
        $response = [
            'SaleToPOIResponse' => [
                'PaymentResponse' => [
                    'PaymentResult' => [
                        'PaymentAcquirerData' => [
                            'AcquirerTransactionID' => [
                                'TransactionID' => '2345678'
                            ]
                        ]
                    ],
                    'Response' => [
                        'Result' => 'Success'
                    ]
                ]
            ]
        ];
        $handlingSubject = ['payment' => $dataObject];
        $this->statusResolver
            ->method('getOrderStatusByState')
            ->with($order, Order::STATE_NEW)
            ->willReturn('pending');
        $order->expects($this->once())->method('setState')->with(Order::STATE_NEW);
        $order->expects($this->once())->method('setStatus')->with('pending');
        $this->quoteHelper->expects($this->once())->method('disableQuote');
        $this->paymentPosCloudHandler->handle($handlingSubject, $response);
    }
}
