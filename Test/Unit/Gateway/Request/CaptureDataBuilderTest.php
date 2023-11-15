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

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\AdyenException;
use Adyen\Payment\Gateway\Request\CaptureDataBuilder;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data as DataHelper;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;

class CaptureDataBuilderTest extends AbstractAdyenTestCase
{
    public function testFullAmountNotAuthorized()
    {
        $this->expectException(AdyenException::class);

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getInvoiceCollection' => $this->createConfiguredMock(InvoiceCollection::class, [
                'getLastItem' => $this->createMock(Invoice::class)
            ])
        ]);
        $paymentMock = $this->createConfiguredMock(\Magento\Sales\Model\Order\Payment::class, [
            'getOrder' => $orderMock
        ]);
        $paymentDataObjectMock = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $paymentMock
        ]);

        $chargedCurrencyHelperMock = $this->createConfiguredMock(ChargedCurrency::class, [
            'getInvoiceAmountCurrency' => $this->createConfiguredMock(AdyenAmountCurrency::class, [
                'getCurrencyCode' => 'EUR',
                'getAmount' => '1000'
            ]),
            'getOrderAmountCurrency' => $this->createConfiguredMock(AdyenAmountCurrency::class, [
                'getAmount' => '1000'
            ])
        ]);

        $adyenOrderPaymentHelperMock = $this->createConfiguredMock(AdyenOrderPayment::class, [
            'isFullAmountAuthorized' => false
        ]);

        $buildSubject = [
            'payment' => $paymentDataObjectMock
        ];

        $captureDataBuilder = $this->buildCaptureDataBuilderObject(
            null,
            $chargedCurrencyHelperMock,
            null,
            $adyenOrderPaymentHelperMock
        );

        $captureDataBuilder->build($buildSubject);
    }

    private function buildCaptureDataBuilderObject(
        $adyenHelperMock = null,
        $chargedCurrencyMock = null,
        $orderPaymentResourceModelMock = null,
        $adyenOrderPaymentHelperMock = null,
        $adyenLoggerMock = null,
        $contextMock = null,
        $openInvoiceHelperMock = null
    ): CaptureDataBuilder {
        if (is_null($adyenHelperMock)) {
            $adyenHelperMock = $this->createPartialMock(DataHelper::class, []);
        }

        if (is_null($chargedCurrencyMock)) {
            $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        }

        if (is_null($orderPaymentResourceModelMock)) {
            $orderPaymentResourceModelMock = $this->createMock(Payment::class);
        }

        if (is_null($adyenOrderPaymentHelperMock)) {
            $adyenOrderPaymentHelperMock = $this->createMock(AdyenOrderPayment::class);
        }

        if (is_null($adyenLoggerMock)) {
            $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        }

        if (is_null($contextMock)) {
            $contextMock = $this->createConfiguredMock(Context::class, [
                'getMessageManager' => $this->createMock(ManagerInterface::class)
            ]);
        }

        if (is_null($openInvoiceHelperMock)) {
            $openInvoiceHelperMock = $this->createMock(OpenInvoice::class);
        }

        return new CaptureDataBuilder(
            $adyenHelperMock,
            $chargedCurrencyMock,
            $adyenOrderPaymentHelperMock,
            $adyenLoggerMock,
            $contextMock,
            $orderPaymentResourceModelMock,
            $openInvoiceHelperMock
        );
    }
}
