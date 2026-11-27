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

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Model\Checkout\BalanceCheckResponse;
use Adyen\Model\Checkout\ObjectSerializer;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\StateData as StateDataResourceModel;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Adyen\Payment\Model\StateDataFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class StateDataTest extends AbstractAdyenTestCase
{
    private StateData $stateDataHelper;
    private StateDataCollection|MockObject $stateDataCollectionMock;
    private StateDataFactory|MockObject $stateDataFactoryMock;
    private StateDataResourceModel|MockObject $stateDataResourceModelMock;
    private CheckoutStateDataValidator|MockObject $checkoutStateDataValidatorMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private OrdersApi|MockObject $ordersApiHelperMock;
    private CartRepositoryInterface|MockObject $quoteRepositoryMock;
    private Data|MockObject $adyenHelperMock;
    private \Adyen\Payment\Model\StateData|MockObject $stateDataMock;

    protected function setUp(): void
    {
        $this->stateDataCollectionMock = $this->createMock(StateDataCollection::class);
        $this->stateDataResourceModelMock = $this->createMock(StateDataResourceModel::class);
        $this->checkoutStateDataValidatorMock = $this->createMock(CheckoutStateDataValidator::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->ordersApiHelperMock = $this->createMock(OrdersApi::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->adyenHelperMock = $this->createMock(Data::class);

        $this->stateDataMock = $this->createMock(\Adyen\Payment\Model\StateData::class);
        $this->stateDataMock->method('setQuoteId')->willReturnSelf();

        $this->stateDataFactoryMock = $this->createGeneratedMock(StateDataFactory::class, ['create']);
        $this->stateDataFactoryMock->method('create')->willReturn($this->stateDataMock);

        $this->stateDataHelper = new StateData(
            $this->stateDataCollectionMock,
            $this->stateDataFactoryMock,
            $this->stateDataResourceModelMock,
            $this->checkoutStateDataValidatorMock,
            $this->adyenLoggerMock,
            $this->ordersApiHelperMock,
            $this->quoteRepositoryMock,
            $this->adyenHelperMock
        );
    }

    public function testSaveStateDataSuccessful()
    {
        $stateData = '{"stateData":"dummyData"}';
        $quoteId = 1;

        $stateDataMock = $this->createConfiguredMock(\Adyen\Payment\Model\StateData::class, [
            'getData' => ['entity_id' => 1, 'quote_id' => 1]
        ]);

        $this->stateDataCollectionMock->method('addFieldToFilter')->willReturnSelf();
        $this->stateDataCollectionMock->method('getFirstItem')->willReturn($stateDataMock);
        $this->stateDataResourceModelMock->expects($this->once())->method('save');

        $this->stateDataHelper->saveStateData($stateData, $quoteId);
    }

    #[DataProvider('saveGiftcardStateDataProvider')]
    public function testSaveGiftcardStateDataUsesServerSideBalance(
        array $stateData,
        string $balanceResponse,
        array $expectedStateData
    ) {
        $quoteId = 1;
        $this->setupGiftcardBalanceCheck($quoteId);

        $this->ordersApiHelperMock->expects($this->once())
            ->method('getGiftcardBalance')
            ->with([
                'paymentMethod' => $stateData['paymentMethod'],
                'amount' => ['currency' => 'EUR', 'value' => 6000]
            ], 2)
            ->willReturn(ObjectSerializer::deserialize($balanceResponse, BalanceCheckResponse::class));

        $this->stateDataMock->expects($this->once())
            ->method('setStateData')
            ->with(json_encode($expectedStateData))
            ->willReturnSelf();
        $this->stateDataResourceModelMock->expects($this->once())->method('save');

        $this->stateDataHelper->saveStateData(json_encode($stateData), $quoteId);
    }

    public static function saveGiftcardStateDataProvider(): array
    {
        $paymentMethod = [
            'type' => 'giftcard',
            'brand' => 'givex',
            'encryptedCardNumber' => 'test_6036280000000000000',
            'encryptedSecurityCode' => 'test_123'
        ];

        return [
            'client balance is overwritten by server balance' => [
                'stateData' => [
                    'paymentMethod' => $paymentMethod,
                    'giftcard' => ['balance' => ['currency' => 'EUR', 'value' => 0], 'title' => 'Givex']
                ],
                'balanceResponse' => '{"resultCode":"NotEnoughBalance","balance":{"currency":"EUR","value":5000}}',
                'expectedStateData' => [
                    'paymentMethod' => $paymentMethod,
                    'giftcard' => ['balance' => ['currency' => 'EUR', 'value' => 5000], 'title' => 'Givex']
                ]
            ],
            'transaction limit lower than balance is used' => [
                'stateData' => [
                    'paymentMethod' => $paymentMethod,
                    'giftcard' => ['balance' => ['currency' => 'EUR', 'value' => 10000], 'title' => 'Givex']
                ],
                'balanceResponse' => '{"resultCode":"Success","balance":{"currency":"EUR","value":10000},' .
                    '"transactionLimit":{"currency":"EUR","value":2000}}',
                'expectedStateData' => [
                    'paymentMethod' => $paymentMethod,
                    'giftcard' => ['balance' => ['currency' => 'EUR', 'value' => 2000], 'title' => 'Givex']
                ]
            ],
            'headless request without giftcard balance' => [
                'stateData' => [
                    'paymentMethod' => $paymentMethod
                ],
                'balanceResponse' => '{"resultCode":"Success","balance":{"currency":"EUR","value":7000}}',
                'expectedStateData' => [
                    'paymentMethod' => $paymentMethod,
                    'giftcard' => ['balance' => ['currency' => 'EUR', 'value' => 7000]]
                ]
            ]
        ];
    }

    public function testSaveNonGiftcardStateDataSkipsBalanceCheck()
    {
        $stateData = [
            'paymentMethod' => [
                'type' => 'scheme',
                'encryptedCardNumber' => 'test_4111111111111111'
            ]
        ];

        $this->checkoutStateDataValidatorMock->method('getValidatedAdditionalData')->willReturnArgument(0);
        $this->ordersApiHelperMock->expects($this->never())->method('getGiftcardBalance');
        $this->stateDataMock->expects($this->once())
            ->method('setStateData')
            ->with(json_encode($stateData))
            ->willReturnSelf();
        $this->stateDataResourceModelMock->expects($this->once())->method('save');

        $this->stateDataHelper->saveStateData(json_encode($stateData), 1);
    }

    #[DataProvider('rejectedGiftcardBalanceProvider')]
    public function testSaveGiftcardStateDataRejectsUnusableBalance(?string $balanceResponse)
    {
        $quoteId = 1;
        $this->setupGiftcardBalanceCheck($quoteId);

        if (is_null($balanceResponse)) {
            $this->ordersApiHelperMock->method('getGiftcardBalance')
                ->willThrowException(new AdyenException('Balance check failed'));
        } else {
            $this->ordersApiHelperMock->method('getGiftcardBalance')
                ->willReturn(ObjectSerializer::deserialize($balanceResponse, BalanceCheckResponse::class));
        }

        $this->stateDataResourceModelMock->expects($this->never())->method('save');
        $this->expectException(LocalizedException::class);

        $this->stateDataHelper->saveStateData(json_encode([
            'paymentMethod' => ['type' => 'giftcard', 'brand' => 'givex'],
            'giftcard' => ['balance' => ['currency' => 'EUR', 'value' => 5000], 'title' => 'Givex']
        ]), $quoteId);
    }

    public static function rejectedGiftcardBalanceProvider(): array
    {
        return [
            'zero balance' => ['{"resultCode":"NotEnoughBalance","balance":{"currency":"EUR","value":0}}'],
            'missing balance' => ['{"resultCode":"Success"}'],
            'balance check failed' => [null]
        ];
    }

    private function setupGiftcardBalanceCheck(int $quoteId): void
    {
        $this->checkoutStateDataValidatorMock->method('getValidatedAdditionalData')->willReturnArgument(0);

        $quoteMock = $this->createMockWithMethods(
            Quote::class,
            ['getStoreId'],
            ['getGrandTotal', 'getQuoteCurrencyCode']
        );
        $quoteMock->method('getGrandTotal')->willReturn(60.00);
        $quoteMock->method('getQuoteCurrencyCode')->willReturn('EUR');
        $quoteMock->method('getStoreId')->willReturn(2);
        $this->quoteRepositoryMock->method('get')->with($quoteId)->willReturn($quoteMock);
        $this->adyenHelperMock->method('formatAmount')->with(60.00, 'EUR')->willReturn(6000);
    }

    public function testRemoveStateDataSuccessful()
    {
        $stateDataId = 1;
        $quoteId = 1;

        $stateDataMock = $this->createConfiguredMock(\Adyen\Payment\Model\StateData::class, [
            'getData' => ['entity_id' => 1, 'quote_id' => 1]
        ]);

        $this->stateDataCollectionMock->method('addFieldToFilter')->willReturnSelf();
        $this->stateDataCollectionMock->method('getFirstItem')->willReturn($stateDataMock);

        $this->assertTrue($this->stateDataHelper->removeStateData($stateDataId, $quoteId));
    }

    public function testRemoveStateDataException()
    {
        $this->expectException(NoSuchEntityException::class);

        $stateDataId = 1;
        $quoteId = 1;

        $stateDataMock = $this->createConfiguredMock(\Adyen\Payment\Model\StateData::class, [
            'getData' => null
        ]);

        $this->stateDataCollectionMock->method('addFieldToFilter')->willReturnSelf();
        $this->stateDataCollectionMock->method('getFirstItem')->willReturn($stateDataMock);

        $this->stateDataHelper->removeStateData($stateDataId, $quoteId);
    }

    #[DataProvider('storedPaymentMethodIdProvider')]
    public function testGetStoredPaymentMethodId($stateData, $expectedResult)
    {
        $this->assertEquals(
            $expectedResult,
            $this->stateDataHelper->getStoredPaymentMethodIdFromStateData($stateData)
        );
    }

    public static function storedPaymentMethodIdProvider(): array
    {
        $mockStoredPaymentMethodId = hash('md5', time());

        return [
            [
                'stateData' => [
                    'paymentMethod' => [
                        'storedPaymentMethodId' => $mockStoredPaymentMethodId
                    ]
                ],
                'expectedResult' => $mockStoredPaymentMethodId
            ],
            [
                'stateData' => [
                    'paymentMethod' => [
                        'storedPaymentMethodId' => null
                    ]
                ],
                'expectedResult' => null
            ],
            [
                'stateData' => [
                    'paymentMethod' => [
                        'type' => 'scheme'
                    ]
                ],
                'expectedResult' => null
            ]
        ];
    }
}
