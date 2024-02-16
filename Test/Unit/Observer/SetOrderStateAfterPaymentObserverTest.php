<?php

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Observer\SetOrderStateAfterPaymentObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order\StatusResolver;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class SetOrderStateAfterPaymentObserverTest extends AbstractAdyenTestCase
{
    private StatusResolver $statusResolver;
    private SetOrderStateAfterPaymentObserver $observer;

    public function setUp(): void
    {
        $this->statusResolver = $this->createMock(StatusResolver::class);
        $this->observer = new SetOrderStateAfterPaymentObserver($this->statusResolver);
    }

    public function testPosPaymentSuccessfulExecute()
    {
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('adyen_pos_cloud');
        $payment->method('getOrder')->willReturn($order);
        $eventObserver = $this->createMock(Observer::class);
        $eventObserver->method('getData')->with('payment')->willReturn($payment);
        $this->statusResolver
            ->method('getOrderStatusByState')
            ->with($order,  Order::STATE_PENDING_PAYMENT)
        ->willReturn('pending');
        $order->expects($this->once())->method('setState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->expects($this->once())->method('setStatus')->willReturn('pending');

        $this->observer->execute($eventObserver);
    }
}
