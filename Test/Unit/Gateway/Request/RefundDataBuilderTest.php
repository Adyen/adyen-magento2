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
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

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

    private static function dataProviderForRefundDataBuilder(): array
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
     * @dataProvider dataProviderForRefundDataBuilder()
     *
     * @param string $paymentMethod
     * @param array $orderPaymentCollectionData
     * @param string|null $refundStrategy
     * @return void
     * @throws LocalizedException
     */
    public function testBuild(
        string $paymentMethod,
        array $orderPaymentCollectionData,
        string $refundStrategy = null
    ) {
        $storeId = 1;
        $paymentId = 10;
        $orderIncrementId = '0000000000101';
        $pspreference = 'XYZ123456789';
        $merchantAccount = 'mock_merchant_account';
        $creditMemoCurrency = 'EUR';
        $creditMemoAmount = 150.00;
        $orderAmount = 200.00;

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

        $orderAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $orderAmountCurrencyMock->method('getAmount')->willReturn($orderAmount);

        $creditMemoAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $creditMemoAmountCurrencyMock->method('getCurrencyCode')->willReturn($creditMemoCurrency);
        $creditMemoAmountCurrencyMock->method('getAmount')->willReturn($creditMemoAmount);

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($orderMock)
            ->willReturn($orderAmountCurrencyMock);

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

            // phpcs:ignore
            $orderPaymentCollectionMock->method('getIterator')->willReturn(new \ArrayObject($objectArray));
        }

        $this->orderPaymentCollectionFactoryMock->method('create')->willReturn($orderPaymentCollectionMock);

        $this->paymentMethodsHelperMock->method('isOpenInvoice')
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
        $this->assertEquals(count($orderPaymentCollectionData), count($result['body']));
        $this->assertArrayHasKey('merchantAccount', $result['body'][0]);
        $this->assertArrayHasKey('amount', $result['body'][0]);
        $this->assertArrayHasKey('reference', $result['body'][0]);
        $this->assertArrayHasKey('paymentPspReference', $result['body'][0]);
    }
}
