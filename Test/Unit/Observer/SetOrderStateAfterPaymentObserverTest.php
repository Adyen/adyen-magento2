<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Model\Method\Adapter;
use Adyen\Payment\Observer\SetOrderStateAfterPaymentObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class SetOrderStateAfterPaymentObserverTest extends AbstractAdyenTestCase
{
    private $setOrderStateAfterPaymentObserver;
    private $observerMock;
    private $paymentMock;
    private $orderMock;
    private $statusResolverMock;

    public function setUp(): void
    {
        $this->observerMock = $this->createMock(Observer::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->statusResolverMock = $this->createMock(Order\StatusResolver::class);

        $paymentMethodInstanceMock = $this->createMock(Adapter::class);
        $this->paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);
        $this->observerMock->method('getData')->with('payment')->willReturn($this->paymentMock);
        $this->statusResolverMock->method('getOrderStatusByState')
            ->willReturn(Order::STATE_PENDING_PAYMENT);

        $this->setOrderStateAfterPaymentObserver = new SetOrderStateAfterPaymentObserver(
            $this->statusResolverMock
        );
    }

    private static function resultCodeProvider(): array
    {
        return [
            [
                'resultCode' => PaymentResponseHandler::REDIRECT_SHOPPER,
                'action' => ['type' => 'TYPE_PLACEHOLDER']
            ],
            [
                'resultCode' => PaymentResponseHandler::CHALLENGE_SHOPPER,
                'action' => ['type' => 'TYPE_PLACEHOLDER']
            ],
            [
                'resultCode' => PaymentResponseHandler::PENDING,
                'action' => ['type' => 'TYPE_PLACEHOLDER']
            ],
            [
                'resultCode' => PaymentResponseHandler::IDENTIFY_SHOPPER,
                'action' => ['type' => 'TYPE_PLACEHOLDER']
            ],
            [
                'resultCode' => PaymentResponseHandler::AUTHORISED,
                'action' => null,
                'changeStatus' => false
            ]
        ];
    }

    /**
     * @dataProvider resultCodeProvider
     * @return void
     * @throws LocalizedException
     */
    public function testExecute($resultCode, $action, $changeStatus = true)
    {
        $this->paymentMock->method('getAdditionalInformation')->will(
            $this->returnValueMap([
                ['resultCode', $resultCode],
                ['action', $action]
            ])
        );

        if ($changeStatus) {
            $this->orderMock->expects($this->once())->method('setState');
            $this->orderMock->expects($this->once())->method('save');
        } else {
            $this->orderMock->expects($this->never())->method('setState');
            $this->orderMock->expects($this->never())->method('save');
        }

        $this->setOrderStateAfterPaymentObserver->execute($this->observerMock);

    }
}
