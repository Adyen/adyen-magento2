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
use Adyen\Payment\Api\AdyenPaymentMethodsBalanceInterface;
use Adyen\Payment\Helper\Data;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class StateDataTest extends AbstractAdyenTestCase
{
    private StateData $stateDataHelper;
    private StateDataCollection|MockObject $stateDataCollectionMock;
    private StateDataFactory|MockObject $stateDataFactoryMock;
    private StateDataResourceModel|MockObject $stateDataResourceModelMock;
    private CheckoutStateDataValidator|MockObject $checkoutStateDataValidatorMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private AdyenPaymentMethodsBalanceInterface|MockObject $paymentMethodsBalanceMock;
    private CartRepositoryInterface|MockObject $quoteRepositoryMock;
    private Data|MockObject $adyenHelperMock;
    private \Adyen\Payment\Model\StateData|MockObject $stateDataMock;

    protected function setUp(): void
    {
        $this->stateDataCollectionMock = $this->createMock(StateDataCollection::class);
        $this->stateDataResourceModelMock = $this->createMock(StateDataResourceModel::class);
        $this->checkoutStateDataValidatorMock = $this->createMock(CheckoutStateDataValidator::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->paymentMethodsBalanceMock = $this->createMock(AdyenPaymentMethodsBalanceInterface::class);
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
            $this->paymentMethodsBalanceMock,
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

        $this->paymentMethodsBalanceMock->expects($this->once())
            ->method('getBalance')
            ->with(json_encode([
                'paymentMethod' => $stateData['paymentMethod'],
                'amount' => ['currency' => 'EUR', 'value' => 6000]
            ]))
            ->willReturn($balanceResponse);

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
        $this->paymentMethodsBalanceMock->expects($this->never())->method('getBalance');
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
            $this->paymentMethodsBalanceMock->method('getBalance')
                ->willThrowException(new AdyenException('Balance check failed'));
        } else {
            $this->paymentMethodsBalanceMock->method('getBalance')->willReturn($balanceResponse);
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

        $quoteMock = $this->createMockWithMethods(Quote::class, [], ['getGrandTotal', 'getQuoteCurrencyCode']);
        $quoteMock->method('getGrandTotal')->willReturn(60.00);
        $quoteMock->method('getQuoteCurrencyCode')->willReturn('EUR');
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

    public function testGetValidatedStateDataFromCcNumberRemovesUnapprovedKeys()
    {
        $stateDataHelper = new StateData(
            $this->stateDataCollectionMock,
            $this->stateDataFactoryMock,
            $this->stateDataResourceModelMock,
            new CheckoutStateDataValidator(),
            $this->adyenLoggerMock,
            $this->paymentMethodsBalanceMock,
            $this->quoteRepositoryMock,
            $this->adyenHelperMock
        );

        $paymentMethod = [
            'type' => 'scheme',
            'encryptedCardNumber' => 'test_4111111111111111'
        ];
        $ccNumber = json_encode([
            'unsupportedField' => 'value',
            'paymentMethod' => $paymentMethod,
            'storePaymentMethod' => true
        ]);

        $this->assertSame(
            ['paymentMethod' => $paymentMethod, 'storePaymentMethod' => true],
            $stateDataHelper->getValidatedStateDataFromCcNumber($ccNumber)
        );
    }

    /**
     * @dataProvider invalidCcNumberProvider
     */
    public function testGetValidatedStateDataFromCcNumberReturnsEmptyArrayForInvalidInput($ccNumber)
    {
        $this->checkoutStateDataValidatorMock->expects($this->never())->method('getValidatedAdditionalData');

        $this->assertSame([], $this->stateDataHelper->getValidatedStateDataFromCcNumber($ccNumber));
    }

    public static function invalidCcNumberProvider(): array
    {
        return [
            ['ccNumber' => null],
            ['ccNumber' => ''],
            ['ccNumber' => 'not-a-json'],
            ['ccNumber' => '1'],
            ['ccNumber' => ['paymentMethod' => ['type' => 'scheme']]]
        ];
    }

    /**
     * @dataProvider storedPaymentMethodIdProvider
     */
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
