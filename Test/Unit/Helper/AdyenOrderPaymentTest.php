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
use Adyen\Payment\Model\AdyenAmountCurrency;
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
        $mockContext = $this->createMock(Context::class);
        $mockLogger = $this->createMock(AdyenLogger::class);
        $this->mockAdyenDataHelper = $this->createMock(Data::class);
        $mockChargedCurrency = $this->createMock(ChargedCurrency::class);
        $this->mockOrderPaymentResourceModel = $this->createMock(Payment::class);
        $mockAdyenOrderPaymentCollection = $this->createGeneratedMock(Payment\CollectionFactory::class);
        $this->mockAdyenOrderPaymentFactory = $this->createGeneratedMock(PaymentFactory::class, ['create']);
        $this->mockInvoiceHelper = $this->createMock(Invoice::class);

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
        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getId' => $paymentId
        ]);
        $order = $this->createConfiguredMock(Order::class, [
            'getPayment' => $payment
        ]);
        $adyenOrderPayment = $this->createConfiguredMock(AdyenPaymentModel::class, [
            'setPspreference' => $pspReference,
            'setMerchantReference' => $merchantReference,
            'setPaymentId' => $paymentId,
            'setCaptureStatus' => AdyenPaymentModel::CAPTURE_STATUS_AUTO_CAPTURE,
            'setAmount' => $amount
        ]);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getPspreference' => $pspReference,
            'getMerchantReference' => $merchantReference
        ]);

        $this->mockAdyenDataHelper->method('originalAmount')->willReturn($amount);
        $this->mockAdyenOrderPaymentFactory->method('create')->willReturn($adyenOrderPayment);
        $result = $this->adyenOrderPaymentHelper->createAdyenOrderPayment($order, $notification, true);
        $this->assertInstanceOf(AdyenPaymentModel::class, $result);
    }

    public function testIsFullAmountFinalized()
    {
        $orderAmountCurrency = new AdyenAmountCurrency(
            10.33,
            'EUR',
            null,
            null,
            10.33
        );

        $mockChargedCurrency = $this->createConfiguredMock(ChargedCurrency::class, [
           'getOrderAmountCurrency' => $orderAmountCurrency
        ]);

        $autoAdyenOrderPayment = $this->createConfiguredMock(AdyenPaymentModel::class, [
            'setAmount' => 10.33
        ]);

        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 55
        ]);

        $order = $this->createConfiguredMock(Order::class, [
            'getPayment' => $payment
        ]);

        $mockOrderPaymentResourceModel = $this->createConfiguredMock(Payment::class, [
            'getLinkedAdyenOrderPayments' => [$autoAdyenOrderPayment]
        ]);

        $mockContext = $this->createMock(Context::class);
        $mockLogger = $this->createMock(AdyenLogger::class);
        $this->mockAdyenDataHelper = $this->createMock(Data::class);
        $mockAdyenOrderPaymentCollection = $this->createGeneratedMock(Payment\CollectionFactory::class);
        $this->mockAdyenOrderPaymentFactory = $this->createGeneratedMock(PaymentFactory::class, ['create']);
        $this->mockInvoiceHelper = $this->createMock(Invoice::class);

        $this->adyenOrderPaymentHelper = new AdyenOrderPayment(
            $mockContext,
            $mockLogger,
            $mockAdyenOrderPaymentCollection,
            $this->mockAdyenDataHelper,
            $mockChargedCurrency,
            $mockOrderPaymentResourceModel,
            $this->mockAdyenOrderPaymentFactory,
            $this->mockInvoiceHelper
        );

        $this->assertTrue($this->adyenOrderPaymentHelper->isFullAMountFinalized($order));
    }
}
