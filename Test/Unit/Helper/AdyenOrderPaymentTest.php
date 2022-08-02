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

namespace Adyen\Payment\Tests\Unit\Helper;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment as AdyenPaymentModel;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

class AdyenOrderPaymentTest extends AbstractAdyenTestCase
{
    /**
     * @var AdyenOrderPayment
     */
    private $adyenOrderPaymentHelper;
    /**
     * @var Payment|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockOrderPaymentResourceModel;
    /**
     * @var Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockAdyenDataHelper;
    /**
     * @var PaymentFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockAdyenOrderPaymentFactory;

    /**
     * @var Invoice|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockInvoiceHelper;

    public function setUp(): void
    {
        $mockContext = $this->getSimpleMock(Context::class);
        $mockLogger = $this->getSimpleMock(AdyenLogger::class);
        $this->mockAdyenDataHelper = $this->getSimpleMock(Data::class);
        $mockChargedCurrency = $this->getSimpleMock(ChargedCurrency::class);
        $this->mockOrderPaymentResourceModel = $this->getSimpleMock(Payment::class);
        $mockAdyenOrderPaymentCollection = $this->createGeneratedMock(Payment\CollectionFactory::class);
        $this->mockAdyenOrderPaymentFactory = $this->createGeneratedMock(PaymentFactory::class, ['create']);
        $this->mockInvoiceHelper = $this->getSimpleMock(Invoice::class);

        $this->adyenOrderPaymentHelper = new AdyenOrderPayment(
            $mockContext,
            $mockLogger,
            $mockAdyenOrderPaymentCollection,
            $this->mockAdyenDataHelper,
            $mockChargedCurrency,
            $this->mockOrderPaymentResourceModel,
            $this->mockAdyenOrderPaymentFactory,
            $this->mockInvoiceHelper
        );
    }

    public function testCreateAdyenOrderPayment()
    {
        $paymentId = 1;
        $merchantReference = 'TestMerchant';
        $pspReference = 'ABCD1234GHJK5678';
        $amount = 10;
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()->getMock();
        $adyenOrderPayment = $this->getMockBuilder(AdyenPaymentModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()->getMock();
        $payment->method('getId')->willReturn($paymentId);
        $order->method('getPayment')->willReturn($payment);
        $notification->method('getPspreference')->willReturn($pspReference);
        $notification->method('getMerchantReference')->willReturn($merchantReference);
        $this->mockAdyenDataHelper->method('originalAmount')->willReturn($amount);
        $this->mockAdyenOrderPaymentFactory->method('create')->willReturn($adyenOrderPayment);
        $adyenOrderPayment->expects($this->once())->method('setPspreference')->with($pspReference);
        $adyenOrderPayment->expects($this->once())->method('setMerchantReference')->with($merchantReference);
        $adyenOrderPayment->expects($this->once())->method('setPaymentId')->with($paymentId);
        $adyenOrderPayment->expects($this->once())->method('setCaptureStatus')->with(AdyenPaymentModel::CAPTURE_STATUS_AUTO_CAPTURE);
        $adyenOrderPayment->expects($this->once())->method('setAmount')->with($amount);
        $result = $this->adyenOrderPaymentHelper->createAdyenOrderPayment($order, $notification, true);
        $this->assertInstanceOf(AdyenPaymentModel::class, $result);
    }
}
