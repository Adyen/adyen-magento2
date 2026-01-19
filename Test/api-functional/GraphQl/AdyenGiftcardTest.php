<?php declare(strict_types=1);
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2026 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\GraphQl;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Test\Fixture\GuestCart;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class AdyenGiftcardTest extends GraphQlAbstract
{
    protected DataFixtureStorage $fixtures;
    protected QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;

    /**
     * @return void
     * @throws LocalizedException
     */
    public function setUp(): void
    {
        parent::setUp();

        $objectManager = Bootstrap::getObjectManager();
        $this->quoteIdToMaskedQuoteId = $objectManager->get(QuoteIdToMaskedQuoteIdInterface::class);
        $this->fixtures = $objectManager->get(DataFixtureStorageManager::class)->getStorage();
    }

    /**
     * Data provider for testBalanceCheck
     *
     * @return array[]
     */
    public static function balanceCheckDataProvider(): array
    {
        return [
            [
                'amount' => 5000,
                'resultCode' => 'Success'
            ],
            [
                'amount' => 75000,
                'resultCode' => 'NotEnoughBalance'
            ]
        ];
    }

    /**
     * This function tests `adyenPaymentMethodsBalance` query.
     *
     * @dataProvider balanceCheckDataProvider
     *
     * @return void
     * @throws Exception
     */
    public function testBalanceCheck(int $amount, string $resultCode)
    {
        $jsonPayload = addslashes(json_encode($this->getBalanceCheckPayload($amount)));

        $query = <<<QUERY
        {
            adyenPaymentMethodsBalance (payload: "$jsonPayload") {
                balanceResponse
            }
        }
        QUERY;

        $response = $this->graphQlQuery($query);

        self::assertArrayHasKey('adyenPaymentMethodsBalance', $response);
        self::assertArrayHasKey('balanceResponse', $response['adyenPaymentMethodsBalance']);

        $decodedBalanceResponse = json_decode(
            $response['adyenPaymentMethodsBalance']['balanceResponse'],
            true
        );

        self::assertArrayHasKey('balance', $decodedBalanceResponse);
        self::assertArrayHasKey('currency', $decodedBalanceResponse['balance']);
        self::assertArrayHasKey('value', $decodedBalanceResponse['balance']);
        self::assertEquals('EUR', $decodedBalanceResponse['balance']['currency']);
        self::assertEquals('5000', $decodedBalanceResponse['balance']['value']);
        self::assertEquals($resultCode, $decodedBalanceResponse['resultCode']);
    }

    /**
     * This function tests `adyenSaveStateData` & `adyenRemoveStateData` mutations and `adyenRedeemedGiftcards` query.
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    #[DataFixture(GuestCart::class, as: 'guestCart1')]
    public function testSaveStateData()
    {
        $cart = $this->fixtures->get('guestCart1');
        $cartId = $this->quoteIdToMaskedQuoteId->execute(intval($cart->getId()));

        // Test saving stateData
        $payload = $this->getValidGiftcardStateData(5000);
        $jsonPayload = addslashes(json_encode($payload));

        $mutationSaveStateData = <<<MUTATION
            mutation {
                adyenSaveStateData(stateData: "$jsonPayload", cartId: "$cartId") {
                    stateDataId
                }
            }
        MUTATION;

        $saveStateDataResponse = $this->graphQlMutation($mutationSaveStateData);
        $stateDataId = $saveStateDataResponse['adyenSaveStateData']['stateDataId'];

        self::assertArrayHasKey('adyenSaveStateData', $saveStateDataResponse);
        self::assertArrayHasKey('stateDataId', $saveStateDataResponse['adyenSaveStateData']);
        self::assertGreaterThan(0, intval($saveStateDataResponse['adyenSaveStateData']['stateDataId']));

        // Test redeemed giftcards
        $mutationGetRedeemedGiftcards = <<<QUERY
            query {
                adyenRedeemedGiftcards(cartId: "$cartId") {
                    redeemedGiftcards {
                        stateDataId
                        brand
                        title
                        balance {
                            currency
                            value
                        }
                        deductedAmount
                    }
                    remainingAmount
                    totalDiscount
                }
            }
        QUERY;

        $redeemedGiftcardsResponse = $this->graphQlQuery($mutationGetRedeemedGiftcards);

        self::assertArrayHasKey('adyenRedeemedGiftcards', $redeemedGiftcardsResponse);
        self::assertArrayHasKey('redeemedGiftcards', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']);
        self::assertArrayHasKey('remainingAmount', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']);
        self::assertArrayHasKey('totalDiscount', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']);
        self::assertArrayHasKey('stateDataId', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]);
        self::assertArrayHasKey('brand', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]);
        self::assertArrayHasKey('title', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]);
        self::assertArrayHasKey('balance', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]);
        self::assertArrayHasKey('deductedAmount', $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]);
        self::assertEquals(
            'EUR',
            $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]['balance']['currency']
        );
        self::assertEquals(
            '5000',
            $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]['balance']['value']
        );
        self::assertEquals(
            'Givex',
            $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]['title']
        );
        self::assertEquals(
            'givex',
            $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]['brand']
        );
        self::assertEquals(
            $stateDataId,
            $redeemedGiftcardsResponse['adyenRedeemedGiftcards']['redeemedGiftcards'][0]['stateDataId']
        );

        // Test removing stateData
        $mutationRemoveStateData = <<<MUTATION
            mutation {
                adyenRemoveStateData(stateDataId: "$stateDataId", cartId: "$cartId") {
                    stateDataId
                }
            }
        MUTATION;

        $removeStateDataResponse = $this->graphQlMutation($mutationRemoveStateData);

        self::assertArrayHasKey('adyenRemoveStateData', $removeStateDataResponse);
        self::assertArrayHasKey('stateDataId', $removeStateDataResponse['adyenRemoveStateData']);
        self::assertEquals($stateDataId, $removeStateDataResponse['adyenRemoveStateData']['stateDataId']);
    }

    /**
     * @param int $amount
     * @return array
     */
    protected function getValidGiftcardStateData(int $amount): array
    {
        return array_merge($this->getBalanceCheckPayload($amount), [
            'giftcard' => [
                'balance' => [
                    'currency' => 'EUR',
                    'value' => 5000
                ],
                'title' => 'Givex'
            ]
        ]);
    }

    /**
     * @param int $amount
     * @return array
     */
    protected function getBalanceCheckPayload(int $amount): array
    {
        return [
            'paymentMethod' => [
                'type' => 'giftcard',
                'brand' => 'givex',
                'number' => '6036280000000000000',
                'cvc' => '123'
            ],
            'amount' => [
                'currency' => 'EUR',
                'value' => $amount
            ]
        ];
    }
}
