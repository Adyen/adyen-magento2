<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Api\Data\InvoiceInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Gateway\Request\RefundDataBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Order\Payment as AdyenOrderPayment;
use Adyen\Payment\Model\ResourceModel\Invoice\Collection as AdyenInvoiceCollection;
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use ArrayIterator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class RefundDataBuilderTest extends AbstractAdyenTestCase
{
    protected ?RefundDataBuilder $refundDataBuilder;
    protected Data $adyenHelperMock;
    protected PaymentCollectionFactory $orderPaymentCollectionFactoryMock;
    protected ChargedCurrency $chargedCurrencyMock;
    protected Config $configHelperMock;
    protected OpenInvoice $openInvoiceHelperMock;
    protected PaymentMethods $paymentMethodsHelperMock;
    protected AdyenInvoiceCollection $adyenInvoiceCollection;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createPartialMock(Data::class, [
            'getAdyenMerchantAccount'
        ]);
        $this->orderPaymentCollectionFactoryMock =
            $this->createGeneratedMock(PaymentCollectionFactory::class, ['create']);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->openInvoiceHelperMock = $this->createMock(OpenInvoice::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->adyenInvoiceCollection = $this->createMock(AdyenInvoiceCollection::class);

        $this->refundDataBuilder = new RefundDataBuilder(
            $this->adyenHelperMock,
            $this->orderPaymentCollectionFactoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->openInvoiceHelperMock,
            $this->paymentMethodsHelperMock,
            $this->adyenInvoiceCollection
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->refundDataBuilder = null;
    }

    public static function dataProviderForRefundDataBuilder(): array
    {
        return [
            [
                'paymentMethod' => 'adyen_cc',
                'orderPaymentCollectionData' => [
                    [
                        'amount' => 100.00,
                        'totalRefunded' => 0.0
                    ]
                ]
            ],
            [
                'paymentMethod' => 'adyen_paypal',
                'orderPaymentCollectionData' => [
                    [
                        'amount' => 100.00,
                        'totalRefunded' => 0.0
                    ]
                ]
            ],
            [
                'paymentMethod' => 'adyen_moto',
                'orderPaymentCollectionData' => [
                    [
                        'amount' => 100.00,
                        'totalRefunded' => 0.0
                    ]
                ]
            ],
            [
                'paymentMethod' => 'adyen_cc',
                'orderPaymentCollectionData' => [
                    [
                        'amount' => 100.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ],
                    [
                        'amount' => 75.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ]
                ],
                'refundStrategy' => '1'
            ],
            [
                'paymentMethod' => 'adyen_cc',
                'orderPaymentCollectionData' => [
                    [
                        'amount' => 100.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ],
                    [
                        'amount' => 75.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ]
                ],
                'refundStrategy' => '2'
            ],
            [
                'paymentMethod' => 'adyen_cc',
                'orderPaymentCollectionData' => [
                    [
                        'amount' => 100.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ],
                    [
                        'amount' => 75.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ],
                    [
                        'amount' => 25.00,
                        'totalRefunded' => 25.0,
                        'pspreference' => 'mock_pspreference'
                    ]
                ],
                'refundStrategy' => '3'
            ],
            [
                'paymentMethod' => 'adyen_cc',
                'orderPaymentCollectionData' => [
                    [
                        'amount' => 100.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ],
                    [
                        'amount' => 75.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ],
                    [
                        'amount' => 300.00,
                        'totalRefunded' => 0.0,
                        'pspreference' => 'mock_pspreference'
                    ]
                ],
                'refundStrategy' => '3'
            ]
        ];
    }

    /**
     *
     * @param string $paymentMethod
     * @param array $orderPaymentCollectionData
     * @param string|null $refundStrategy
     * @return void
     * @throws LocalizedException
     */
    #[DataProvider('dataProviderForRefundDataBuilder')]
    public function testBuild(
        string $paymentMethod,
        array $orderPaymentCollectionData,
        ?string $refundStrategy = null
    ) {
        $storeId = 1;
        $paymentId = 10;
        $orderIncrementId = '0000000000101';
        $pspreference = 'XYZ123456789';
        $merchantAccount = 'mock_merchant_account';
        $creditMemoCurrency = 'EUR';
        $creditMemoAmount = 150.00;

        $this->configHelperMock->method('getAdyenAbstractConfigData')
            ->with('partial_payments_refund_strategy', $storeId)
            ->willReturn($refundStrategy);

        $creditMemoMock = $this->createMock(Order\Creditmemo::class);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getIncrementId')->willReturn($orderIncrementId);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getId')->willReturn($paymentId);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getCreditmemo')->willReturn($creditMemoMock);
        $paymentMock->method('getMethod')->willReturn($paymentMethod);
        $paymentMock->method('getCcTransId')->willReturn($pspreference);
        $paymentMock->method('getAdditionalInformation')
            ->with('motoMerchantAccount')
            ->willReturn($merchantAccount);

        $creditMemoAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $creditMemoAmountCurrencyMock->method('getCurrencyCode')->willReturn($creditMemoCurrency);
        $creditMemoAmountCurrencyMock->method('getAmount')->willReturn($creditMemoAmount);

        $adyenInvoiceMock = [
            InvoiceInterface::ENTITY_ID => 1,
            InvoiceInterface::PSPREFERENCE => 'mock_capture_pspreference'
        ];

        $this->adyenInvoiceCollection->method('getAdyenInvoicesLinkedToMagentoInvoice')
            ->willReturn([$adyenInvoiceMock]);
        $this->configHelperMock->method('getConfigData')->willReturnMap([
            ['paypal_capture_mode', 'adyen_abstract', $storeId, true, true],
        ]);

        $this->chargedCurrencyMock->method('getCreditMemoAmountCurrency')
            ->with($creditMemoMock)
            ->willReturn($creditMemoAmountCurrencyMock);

        $this->adyenHelperMock->method('getAdyenMerchantAccount')
            ->with($paymentMethod, $storeId)
            ->willReturn($merchantAccount);

        $orderPaymentCollectionMock = $this->createMock(Collection::class);
        $orderPaymentCollectionMock->method('addFieldToFilter')
            ->with(OrderPaymentInterface::PAYMENT_ID, $paymentId)
            ->willReturnSelf();
        $orderPaymentCollectionMock->method('getSize')->willReturn(count($orderPaymentCollectionData));

        if (count($orderPaymentCollectionData) > 1) {
            $objectArray = [];

            foreach ($orderPaymentCollectionData as $orderPayment) {
                $objectArray[] = $this->createConfiguredMock(AdyenOrderPayment::class, [
                    'getAmount' => $orderPayment['amount'],
                    'getTotalRefunded' => $orderPayment['totalRefunded'],
                    'getPspreference' => $orderPayment['pspreference']
                ]);
            }

            $orderPaymentCollectionMock->method('getIterator')->willReturn(new ArrayIterator($objectArray));
        }

        $this->orderPaymentCollectionFactoryMock->method('create')->willReturn($orderPaymentCollectionMock);

        $this->paymentMethodsHelperMock->method('isOpenInvoice')
            ->with($paymentMethodInstanceMock)
            ->willReturn(true);

        $this->paymentMethodsHelperMock->method('getRefundRequiresCapturePspreference')
            ->with($paymentMethodInstanceMock)
            ->willReturn(true);

        $this->openInvoiceHelperMock->method('getOpenInvoiceDataForCreditMemo')
            ->with($creditMemoMock)
            ->willReturn(['lineItems' => [['product_id' => 1]]]);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $result = $this->refundDataBuilder->build($buildSubject);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('clientConfig', $result);
        $this->assertEquals($storeId, $result['clientConfig']['storeId']);
        $this->assertArrayHasKey('body', $result);
        $refundablePayments = array_filter(
            $orderPaymentCollectionData,
            fn (array $orderPayment) => $orderPayment['amount'] > $orderPayment['totalRefunded']
        );
        $this->assertEquals(count($refundablePayments), count($result['body']));
        $this->assertArrayHasKey('merchantAccount', $result['body'][0]);
        $this->assertArrayHasKey('amount', $result['body'][0]);
        $this->assertArrayHasKey('reference', $result['body'][0]);
        $this->assertArrayHasKey('paymentPspReference', $result['body'][0]);
        if ($paymentMethod === PaymentMethods::ADYEN_PAYPAL) {
            $this->assertArrayHasKey('capturePspReference', $result['body'][0]);
        }
    }

    public static function dataProviderForRatioRefundOverMultipleCreditMemos(): array
    {
        return [
            'gift card and card, two credit memos' => [
                'paymentAmounts' => [20.00, 23.75],
                'creditMemos' => [
                    ['amount' => 19.90, 'expectedValues' => [910, 1080]],
                    ['amount' => 23.85, 'expectedValues' => [1090, 1295]]
                ]
            ],
            'uneven amounts, three credit memos' => [
                'paymentAmounts' => [33.33, 66.67],
                'creditMemos' => [
                    ['amount' => 10.00, 'expectedValues' => [333, 667]],
                    ['amount' => 10.01, 'expectedValues' => [334, 667]],
                    ['amount' => 79.99, 'expectedValues' => [2666, 5333]]
                ]
            ],
            'three payments, three credit memos' => [
                'paymentAmounts' => [10.00, 15.00, 25.00],
                'creditMemos' => [
                    ['amount' => 12.34, 'expectedValues' => [247, 370, 617]],
                    ['amount' => 20.00, 'expectedValues' => [400, 600, 1000]],
                    ['amount' => 17.66, 'expectedValues' => [353, 530, 883]]
                ]
            ],
            'equal payments, parts rounded to one cent less than the credit memo' => [
                'paymentAmounts' => [10.00, 10.00, 10.00],
                'creditMemos' => [
                    ['amount' => 10.00, 'expectedValues' => [333, 334, 333]],
                    ['amount' => 20.00, 'expectedValues' => [667, 666, 667]]
                ]
            ],
            'currency without decimals' => [
                'paymentAmounts' => [1000, 2001],
                'creditMemos' => [
                    ['amount' => 1000, 'expectedValues' => [333, 667]],
                    ['amount' => 2001, 'expectedValues' => [667, 1334]]
                ],
                'currency' => 'JPY'
            ],
            'currency with three decimals' => [
                'paymentAmounts' => [1.250, 3.333],
                'creditMemos' => [
                    ['amount' => 2.000, 'expectedValues' => [545, 1455]],
                    ['amount' => 2.583, 'expectedValues' => [705, 1878]]
                ],
                'currency' => 'KWD'
            ],
            'gift card already refunded in full' => [
                'paymentAmounts' => [20.00, 23.75],
                'creditMemos' => [
                    ['amount' => 23.75, 'expectedValues' => [0, 2375]]
                ],
                'refundedValuesBefore' => [2000, 0]
            ],
            'gift card refunded more than its amount' => [
                'paymentAmounts' => [20.00, 23.75],
                'creditMemos' => [
                    ['amount' => 18.75, 'expectedValues' => [0, 1875]]
                ],
                'refundedValuesBefore' => [2500, 0]
            ],
            'credit memo larger than what is left on the payments' => [
                'paymentAmounts' => [20.00, 23.75],
                'creditMemos' => [
                    ['amount' => 30.00, 'expectedValues' => [1000, 1375]]
                ],
                'refundedValuesBefore' => [1000, 1000]
            ]
        ];
    }

    /**
     * @param array $paymentAmounts
     * @param array $creditMemos
     * @param array|null $refundedValuesBefore
     * @param string $currency
     * @return void
     * @throws LocalizedException
     */
    #[DataProvider('dataProviderForRatioRefundOverMultipleCreditMemos')]
    public function testBuildRatioRefundOverMultipleCreditMemos(
        array $paymentAmounts,
        array $creditMemos,
        ?array $refundedValuesBefore = null,
        string $currency = 'EUR'
    ): void {
        $minorUnits = ['JPY' => 1, 'KWD' => 1000][$currency] ?? 100;
        $toMinor = fn (float $amount): int => (int) round($amount * $minorUnits);
        $refundedValues = $refundedValuesBefore ?? array_fill(0, count($paymentAmounts), 0);

        foreach ($creditMemos as $creditMemo) {
            $payments = [];
            $leftValues = [];
            foreach ($paymentAmounts as $index => $paymentAmount) {
                $payments[] = [
                    'amount' => (float) $paymentAmount,
                    'totalRefunded' => (float) $refundedValues[$index] / $minorUnits,
                    'pspreference' => 'psp_' . $index
                ];
                $leftValues[] = max(0, $toMinor($paymentAmount) - $refundedValues[$index]);
            }

            $body = $this->buildRatioRefund($payments, $creditMemo['amount'], $currency);

            $values = array_fill(0, count($paymentAmounts), 0);
            foreach ($body as $request) {
                $this->assertSame($currency, $request['amount']['currency']);
                $this->assertGreaterThan(0, $request['amount']['value']);
                $values[(int) substr($request['paymentPspReference'], 4)] = $request['amount']['value'];
            }
            $this->assertCount(count(array_filter($creditMemo['expectedValues'])), $body);
            $this->assertSame($creditMemo['expectedValues'], $values);

            $memoValue = $toMinor($creditMemo['amount']);
            if ($memoValue <= array_sum($leftValues)) {
                $this->assertSame($memoValue, array_sum($values));
            } else {
                $this->assertSame(array_sum($leftValues), array_sum($values));
            }

            foreach ($values as $index => $value) {
                $this->assertLessThanOrEqual($leftValues[$index], $value);
                $refundedValues[$index] += $value;
            }
        }

        if ($refundedValuesBefore === null) {
            $this->assertSame(array_map($toMinor, $paymentAmounts), $refundedValues);
        }
    }

    /**
     * @param array $payments
     * @param float $creditMemoAmount
     * @param string $currency
     * @return array
     * @throws LocalizedException
     */
    private function buildRatioRefund(array $payments, float $creditMemoAmount, string $currency): array
    {
        $storeId = 1;
        $paymentId = 10;

        $this->configHelperMock->method('getAdyenAbstractConfigData')
            ->with('partial_payments_refund_strategy', $storeId)
            ->willReturn(RefundDataBuilder::REFUND_STRATEGY_BASED_ON_RATIO);
        $this->adyenHelperMock->method('getAdyenMerchantAccount')->willReturn('mock_merchant_account');

        $creditMemoMock = $this->createMock(Order\Creditmemo::class);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getIncrementId')->willReturn('0000000000101');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getId')->willReturn($paymentId);
        $paymentMock->method('getMethodInstance')->willReturn($this->createMock(MethodInterface::class));
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getCreditmemo')->willReturn($creditMemoMock);
        $paymentMock->method('getMethod')->willReturn('adyen_giftcard');

        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $chargedCurrencyMock->method('getCreditMemoAmountCurrency')->willReturn(
            $this->createConfiguredMock(AdyenAmountCurrency::class, [
                'getAmount' => $creditMemoAmount,
                'getCurrencyCode' => $currency
            ])
        );

        $orderPayments = [];
        foreach ($payments as $payment) {
            $orderPayments[] = $this->createConfiguredMock(AdyenOrderPayment::class, [
                'getAmount' => $payment['amount'],
                'getTotalRefunded' => $payment['totalRefunded'],
                'getPspreference' => $payment['pspreference']
            ]);
        }

        $orderPaymentCollectionMock = $this->createMock(Collection::class);
        $orderPaymentCollectionMock->method('addFieldToFilter')->willReturnSelf();
        $orderPaymentCollectionMock->method('getSize')->willReturn(count($orderPayments));
        $orderPaymentCollectionMock->method('getIterator')->willReturn(new ArrayIterator($orderPayments));

        $orderPaymentCollectionFactoryMock = $this->createGeneratedMock(PaymentCollectionFactory::class, ['create']);
        $orderPaymentCollectionFactoryMock->method('create')->willReturn($orderPaymentCollectionMock);

        $refundDataBuilder = new RefundDataBuilder(
            $this->adyenHelperMock,
            $orderPaymentCollectionFactoryMock,
            $chargedCurrencyMock,
            $this->configHelperMock,
            $this->openInvoiceHelperMock,
            $this->paymentMethodsHelperMock,
            $this->adyenInvoiceCollection
        );

        $result = $refundDataBuilder->build([
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, ['getPayment' => $paymentMock])
        ]);

        return $result['body'];
    }
}
