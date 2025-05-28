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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Observer\SubmitQuoteObserver;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class SubmitQuoteObserverTest extends AbstractAdyenTestCase
{
    private $paymentMethodsHelperMock;
    private $configHelperMock;
    private $submitQuoteObserver;
    private $observerMock;
    private $orderMock;
    private $paymentMock;
    private $quoteMock;
    private $eventMock;

    protected function setUp(): void
    {
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->observerMock = $this->createMock(Observer::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->eventMock = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->addMethods(['getOrder', 'getQuote'])
            ->getMock();

        $this->submitQuoteObserver = new SubmitQuoteObserver(
            $this->paymentMethodsHelperMock,
            $this->configHelperMock
        );
    }

    public function testObserverPaymentMethodRequiresAction()
    {
        $this->configHelperMock->method('getAdyenPosCloudPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);

        $this->eventMock->method('getOrder')->willReturn($this->orderMock);
        $this->eventMock->method('getQuote')->willReturn($this->quoteMock);

        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStoreId')->willReturn(1);

        $this->paymentMock->method('getMethod')->willReturn('adyen_method');
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);
        $this->paymentMock->method('getAdditionalInformation')->with('resultCode')
            ->willReturn(PaymentResponseHandler::ACTION_REQUIRED_STATUSES[0]);

        $this->paymentMethodsHelperMock->method('isAdyenPayment')->with('adyen_method')
            ->willReturn(true);

        $this->quoteMock->expects($this->once())->method('setIsActive')->with(true);

        $this->submitQuoteObserver->execute($this->observerMock);
    }

    public function testObserverPaymentMethodNotRequiresAction()
    {
        // Setup test scenario
        $resultCode = 'Authorised'; // Assuming 'Authorised' is not in ACTION_REQUIRED_STATUSES

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);

        $this->eventMock->method('getOrder')->willReturn($this->orderMock);
        $this->eventMock->method('getQuote')->willReturn($this->quoteMock);

        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStoreId')->willReturn(1);

        $this->paymentMock->method('getMethod')->willReturn('adyen_method');
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);
        $this->paymentMock->method('getAdditionalInformation')->with('resultCode')
            ->willReturn($resultCode);

        $this->paymentMethodsHelperMock->method('isAdyenPayment')->with('adyen_method')
            ->willReturn(true);

        // Execute method of the tested class
        $this->submitQuoteObserver->execute($this->observerMock);

        // Assert conditions
        // Verify that the quote's setIsActive method is not called, as the cart status should not be altered
        $this->quoteMock->expects($this->never())->method('setIsActive');
    }
}
