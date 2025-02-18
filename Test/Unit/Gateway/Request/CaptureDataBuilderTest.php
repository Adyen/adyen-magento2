<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Gateway\Request\CaptureDataBuilder;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Data as DataHelper;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;

class CaptureDataBuilderTest extends AbstractAdyenTestCase
{
    public static function adyenOrderPaymentsProvider(): array
    {
        return [
            [
                '$adyenOrderPayments' => [
                    [
                        OrderPaymentInterface::ENTITY_ID => 1,
                        OrderPaymentInterface::AMOUNT => 100,
                        OrderPaymentInterface::TOTAL_CAPTURED => 0,
                        OrderPaymentInterface::PSPREFRENCE => 'ABC123456789XYZ',
                        OrderPaymentInterface::PAYMENT_METHOD => 'visa'
                    ]
                ],
                '$fullAmountAuthorized' => true
            ],
            [
                '$adyenOrderPayments' => [
                    [
                        OrderPaymentInterface::ENTITY_ID => 1,
                        OrderPaymentInterface::AMOUNT => 400,
                        OrderPaymentInterface::TOTAL_CAPTURED => 0,
                        OrderPaymentInterface::PSPREFRENCE => 'ABC123456789XYZ',
                        OrderPaymentInterface::PAYMENT_METHOD => 'svs'
                    ],
                    [
                        OrderPaymentInterface::ENTITY_ID => 2,
                        OrderPaymentInterface::AMOUNT => 600,
                        OrderPaymentInterface::TOTAL_CAPTURED => 0,
                        OrderPaymentInterface::PSPREFRENCE => 'XYZ123456789ABC',
                        OrderPaymentInterface::PAYMENT_METHOD => 'klarna'
                    ]
                ],
                '$fullAmountAuthorized' => true
            ],
            [
                '$adyenOrderPayments' => [],
                '$fullAmountAuthorized' => false
            ],
        ];
    }

    /**
    * @dataProvider adyenOrderPaymentsProvider
    */
    public function testBuildCaptureRequest($adyenOrderPayments, $fullAmountAuthorized)
    {
        $adyenHelperMock = $this->createPartialMock(Data::class, []);

        $lineItems = [
            'id' => PHP_INT_MAX
        ];

        $openInvoiceHelperMock = $this->createMock(OpenInvoice::class);
        $openInvoiceHelperMock->method('getOpenInvoiceDataForInvoice')->willReturn($lineItems);

        if (!$fullAmountAuthorized) {
            $this->expectException(AdyenException::class);
        }

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getInvoiceCollection' => $this->createConfiguredMock(InvoiceCollection::class, [
                'getLastItem' => $this->createMock(Invoice::class)
            ]),
            'getIncrementId' => '00000000001',
            'getTotalInvoiced' => 0
        ]);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createConfiguredMock(\Magento\Sales\Model\Order\Payment::class, [
            'getOrder' => $orderMock,
            'getMethodInstance' => $paymentMethodInstanceMock,
            'getCcTransId' => 'ABC123456789XYZ'
        ]);
        $paymentDataObjectMock = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $paymentMock
        ]);

        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsHelperMock->method('isOpenInvoice')
            ->with($paymentMethodInstanceMock)
            ->willReturn(true);

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
            'isFullAmountAuthorized' => $fullAmountAuthorized
        ]);

        $orderPaymentResourceModelMock = $this->createConfiguredMock(Payment::class, [
            'getLinkedAdyenOrderPayments' => $adyenOrderPayments
        ]);

        $buildSubject = [
            'payment' => $paymentDataObjectMock
        ];

        $captureDataBuilder = $this->buildCaptureDataBuilderObject(
            $adyenHelperMock,
            $chargedCurrencyHelperMock,
            $orderPaymentResourceModelMock,
            $adyenOrderPaymentHelperMock,
            null,
            null,
            $openInvoiceHelperMock,
            $paymentMethodsHelperMock
        );

        $request = $captureDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('body', $request);
        $this->assertArrayHasKey('clientConfig', $request);

        if (count($adyenOrderPayments) > 1) {
            $this->assertArrayHasKey(TransactionCapture::MULTIPLE_AUTHORIZATIONS, $request['body']);
            $this->assertSame(
                count($adyenOrderPayments),
                count($request['body'][TransactionCapture::MULTIPLE_AUTHORIZATIONS])
            );
            $this->assertArrayHasKey('amount', $request['body'][TransactionCapture::MULTIPLE_AUTHORIZATIONS][0]);
            $this->assertArrayHasKey('reference', $request['body'][TransactionCapture::MULTIPLE_AUTHORIZATIONS][0]);
            $this->assertArrayHasKey(
                'paymentPspReference',
                $request['body'][TransactionCapture::MULTIPLE_AUTHORIZATIONS][0]
            );
        } else {
            $this->assertArrayNotHasKey(TransactionCapture::MULTIPLE_AUTHORIZATIONS, $request['body']);

            $this->assertArrayHasKey('amount', $request['body']);
            $this->assertArrayHasKey('reference', $request['body']);
            $this->assertArrayHasKey('paymentPspReference', $request['body']);
        }
    }

    private function buildCaptureDataBuilderObject(
        $adyenHelperMock = null,
        $chargedCurrencyMock = null,
        $orderPaymentResourceModelMock = null,
        $adyenOrderPaymentHelperMock = null,
        $adyenLoggerMock = null,
        $contextMock = null,
        $openInvoiceHelperMock = null,
        $paymentMethodsHelperMock = null
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

        if (is_null($paymentMethodsHelperMock)) {
            $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        }

        return new CaptureDataBuilder(
            $adyenHelperMock,
            $chargedCurrencyMock,
            $adyenOrderPaymentHelperMock,
            $adyenLoggerMock,
            $contextMock,
            $orderPaymentResourceModelMock,
            $openInvoiceHelperMock,
            $paymentMethodsHelperMock
        );
    }
}
