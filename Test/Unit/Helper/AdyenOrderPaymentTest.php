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

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
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
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

class AdyenOrderPaymentTest extends AbstractAdyenTestCase
{
    public function testCreateAdyenOrderPayment()
    {
        $paymentId = 1;
        $merchantReference = 'TestMerchant';
        $pspReference = 'ABCD1234GHJK5678';
        $amount = 10;
        $paymentMethod = 'ideal';
        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getId' => $paymentId
        ]);
        $order = $this->createConfiguredMock(Order::class, [
            'getPayment' => $payment
        ]);
        $adyenOrderPayment = $this->createMock(AdyenPaymentModel::class);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getPspreference' => $pspReference,
            'getMerchantReference' => $merchantReference,
            'getPaymentMethod' => $paymentMethod
        ]);

        $mockAdyenDataHelper = $this->createGeneratedMock(Data::class, ['originalAmount']);

        $mockAdyenOrderPaymentFactory = $this->createGeneratedMock(PaymentFactory::class, ['create']);

        $adyenOrderPaymentHelper = $this->createAdyenOrderPaymentHelper(
            null,
            null,
            $mockAdyenDataHelper,
            null,
            null,
            $mockAdyenOrderPaymentFactory,
            null
        );

        $mockAdyenDataHelper->method('originalAmount')->willReturn($amount);
        $mockAdyenOrderPaymentFactory->method('create')->willReturn($adyenOrderPayment);
        $result = $adyenOrderPaymentHelper->createAdyenOrderPayment($order, $notification, true);
        $this->assertInstanceOf(AdyenPaymentModel::class, $result);
    }

    public function testIsFullAmountFinalizedAutoCapture()
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

        $adyenOrderPayment = [OrderPaymentInterface::AMOUNT => 10.33];

        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 55
        ]);

        $order = $this->createConfiguredMock(Order::class, [
            'getPayment' => $payment
        ]);

        $mockOrderPaymentResourceModel = $this->createConfiguredMock(Payment::class, [
            'getLinkedAdyenOrderPayments' => [$adyenOrderPayment]
        ]);

        $mockAdyenDataHelper = $this->createPartialMock(Data::class, []);

        $adyenOrderPaymentHelper = $this->createAdyenOrderPaymentHelper(
            null,
            null,
            $mockAdyenDataHelper,
            $mockChargedCurrency,
            $mockOrderPaymentResourceModel
        );

        $this->assertTrue($adyenOrderPaymentHelper->isFullAMountFinalized($order));
    }

    public function testIsFullAmountFinalizedManualCapture()
    {
        $invoice = $this->createConfiguredMock(Order\Invoice::class, [
            'getGrandTotal' => 10.33,
            'getOrderCurrencyCode' => 'EUR'
        ]);

        $mockInvoiceHelper = $this->createConfiguredMock(Invoice::class, [
            'isFullInvoiceAmountManuallyCaptured' => true
        ]);

        $mockAdyenDataHelper = $this->createPartialMock(Data::class, []);

        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 55
        ]);

        $order = $this->createConfiguredMock(Order::class, [
            'getInvoiceCollection' => [$invoice],
            'getPayment' => $payment
        ]);

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

        $adyenOrderPaymentHelper = $this->createAdyenOrderPaymentHelper(
            null,
            null,
            $mockAdyenDataHelper,
            $mockChargedCurrency,
            null,
            null,
            $mockInvoiceHelper,
        );

        $this->assertTrue($adyenOrderPaymentHelper->isFullAmountFinalized($order));
    }

    public function testIsFullAmountNotFinalizedManualCapture()
    {
        $invoice = $this->createConfiguredMock(Order\Invoice::class, [
            'getGrandTotal' => 10.53,
            'getOrderCurrencyCode' => 'EUR'
        ]);

        $mockInvoiceHelper = $this->createConfiguredMock(Invoice::class, [
            'isFullInvoiceAmountManuallyCaptured' => true
        ]);

        $mockAdyenDataHelper = $this->createPartialMock(Data::class, []);

        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 55
        ]);

        $order = $this->createConfiguredMock(Order::class, [
            'getInvoiceCollection' => [$invoice],
            'getPayment' => $payment
        ]);

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

        $adyenOrderPaymentHelper = $this->createAdyenOrderPaymentHelper(
            null,
            null,
            $mockAdyenDataHelper,
            $mockChargedCurrency,
            null,
            null,
            $mockInvoiceHelper,
        );

        $this->assertFalse($adyenOrderPaymentHelper->isFullAmountFinalized($order));
    }

    public function testIsFullAmountAuthorized()
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

        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getEntityId' => 55
        ]);

        $adyenOrderPayment = [OrderPaymentInterface::AMOUNT => 10.33];

        $order = $this->createConfiguredMock(Order::class, [
            'getPayment' => $payment
        ]);

        $mockOrderPaymentResourceModel = $this->createConfiguredMock(Payment::class, [
            'getLinkedAdyenOrderPayments' => [$adyenOrderPayment]
        ]);

        $mockAdyenDataHelper = $this->createPartialMock(Data::class, []);

        $adyenOrderPaymentHelper = $this->createAdyenOrderPaymentHelper(
            null,
            null,
            $mockAdyenDataHelper,
            $mockChargedCurrency,
            $mockOrderPaymentResourceModel
        );

        $this->assertTrue($adyenOrderPaymentHelper->isFullAmountAuthorized($order));
    }

    public function testRefundAdyenOrderPayment()
    {
        $payment = $this->createMock(\Adyen\Payment\Model\Order\Payment::class);

        $payment->expects($this->once())->method('save');

        $notification = $this->createMock(Notification::class);

        $mockAdyenDataHelper = $this->createGeneratedMock(Data::class, ['originalAmount']);

        $adyenOrderPaymentHelper = $this->createAdyenOrderPaymentHelper(
            null,
            null,
            $mockAdyenDataHelper
        );

        $result = $adyenOrderPaymentHelper->refundAdyenOrderPayment($payment, $notification);

        $this->assertSame($payment, $result);
    }

    protected function createAdyenOrderPaymentHelper(
        $mockLogger = null,
        $mockAdyenOrderPaymentCollection = null,
        $mockAdyenDataHelper = null,
        $mockChargedCurrency = null,
        $mockOrderPaymentResourceModel = null,
        $mockAdyenOrderPaymentFactory = null,
        $mockInvoiceHelper = null
    ): AdyenOrderPayment {
        $mockContext = $this->createMock(Context::class);

        if (is_null($mockLogger)) {
            $mockLogger = $this->createMock(AdyenLogger::class);
        }

        if (is_null($mockAdyenOrderPaymentCollection)) {
            $mockAdyenOrderPaymentCollection = $this->createGeneratedMock(Payment\CollectionFactory::class);
        }

        if (is_null($mockAdyenDataHelper)) {
            $mockAdyenDataHelper = $this->createMock(Data::class);
        }

        if (is_null($mockChargedCurrency)) {
            $mockChargedCurrency = $this->createMock(ChargedCurrency::class);
        }

        if (is_null($mockOrderPaymentResourceModel)) {
            $mockOrderPaymentResourceModel = $this->createMock(Payment::class);
        }

        if (is_null($mockAdyenOrderPaymentFactory)) {
            $mockAdyenOrderPaymentFactory = $this->createGeneratedMock(PaymentFactory::class, ['create']);
        }

        if (is_null($mockInvoiceHelper)) {
            $mockInvoiceHelper = $this->createMock(Invoice::class);
        }

        return new AdyenOrderPayment(
            $mockContext,
            $mockLogger,
            $mockAdyenOrderPaymentCollection,
            $mockAdyenDataHelper,
            $mockChargedCurrency,
            $mockOrderPaymentResourceModel,
            $mockAdyenOrderPaymentFactory,
            $mockInvoiceHelper
        );
    }
}
