<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Webapi;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;

class GiftCardTest extends WebapiAbstract
{
    protected GetQuoteByReservedOrderId $getQuoteByReservedOrderId;
    protected CustomerTokenServiceInterface $customerTokenService;
    protected QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;

    /**
     * Set up the required dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->getQuoteByReservedOrderId = $objectManager->get(GetQuoteByReservedOrderId::class);
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->quoteIdToMaskedQuoteId = $objectManager->get(QuoteIdToMaskedQuoteIdInterface::class);
    }

    /**
     * Tests the following endpoint:
     * - [POST]     /V1/adyen/payment-methods/balance
     *
     * @return void
     */
    public function testBalanceCheckSuccess()
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/payment-methods/balance",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $response = $this->_webApiCall(
            $serviceInfo,
            ['payload' => json_encode($this->getBalanceRequest(5000))]
        );

        $decodedResponse = json_decode($response, true);
        $this->assertJson($response);
        $this->assertArrayHasKey('pspReference', $decodedResponse);
        $this->assertIsString($decodedResponse['pspReference']);
        $this->assertArrayHasKey('resultCode', $decodedResponse);
        $this->assertEquals('Success', $decodedResponse['resultCode']);
        $this->assertArrayHasKey('balance', $decodedResponse);
        $this->assertArrayHasKey('currency', $decodedResponse['balance']);
        $this->assertArrayHasKey('value', $decodedResponse['balance']);
        $this->assertEquals('EUR', $decodedResponse['balance']['currency']);
        $this->assertEquals(5000, $decodedResponse['balance']['value']);
    }

    /**
     * Tests the following endpoint for insufficient balance:
     * - [POST]     /V1/adyen/payment-methods/balance
     *
     * @return void
     */
    public function testBalanceCheckInsufficientBalance()
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/payment-methods/balance",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $response = $this->_webApiCall(
            $serviceInfo,
            ['payload' => json_encode($this->getBalanceRequest(100000))]
        );
        $decodedResponse = json_decode($response, true);

        $this->assertJson($response);
        $this->assertArrayHasKey('pspReference', $decodedResponse);
        $this->assertIsString($decodedResponse['pspReference']);
        $this->assertArrayHasKey('resultCode', $decodedResponse);
        $this->assertEquals('NotEnoughBalance', $decodedResponse['resultCode']);
        $this->assertArrayHasKey('balance', $decodedResponse);
        $this->assertArrayHasKey('currency', $decodedResponse['balance']);
        $this->assertArrayHasKey('value', $decodedResponse['balance']);
        $this->assertEquals('EUR', $decodedResponse['balance']['currency']);
        $this->assertEquals(5000, $decodedResponse['balance']['value']);
    }

    /**
     * Composite tests the following endpoints:
     * - [POST]     /V1/adyen/guest-carts/:cartId/state-data
     * - [GET]      /V1/adyen/giftcards/guest-carts/:cartId
     * - [DELETE]   /V1/adyen/guest-carts/:cartId/state-data/:stateDataId
     *
     * @magentoDataFixture Magento/Quote/_files/empty_quote.php
     */
    public function testSaveReadRemoveStateDataGuest()
    {
        $cart = $this->getQuoteByReservedOrderId->execute('reserved_order_id');
        $cartId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());

        $saveStateDataServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/guest-carts/{$cartId}/state-data",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $saveStateDataResponse = $this->_webApiCall(
            $saveStateDataServiceInfo,
            ['stateData' => json_encode($this->getValidGiftcardStateData())]
        );
        $this->assertIsInt($saveStateDataResponse);

        $getGiftcardsServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/giftcards/guest-carts/{$cartId}",
                'httpMethod' => Request::HTTP_METHOD_GET
            ]
        ];

        $getGiftcardsResponse = $this->_webApiCall($getGiftcardsServiceInfo);
        $this->assertGetRedeemedGiftcardsResponse($getGiftcardsResponse);

        $removeStateDataServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/guest-carts/{$cartId}/state-data/{$saveStateDataResponse}",
                'httpMethod' => Request::HTTP_METHOD_DELETE
            ]
        ];

        $removeStateDataResponse = $this->_webApiCall($removeStateDataServiceInfo);
        $this->assertTrue($removeStateDataResponse);
    }

    /**
     * Composite tests the following endpoints:
     * - [POST]     /V1/adyen/carts/mine/state-data
     * - [GET]      /V1/adyen/giftcards/mine
     * - [DELETE]   /V1/adyen/carts/mine/state-data/:stateDataId
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Customer/_files/quote.php
     */
    public function testSaveReadRemoveStateDataCustomer()
    {
        $customerToken = $this->customerTokenService->createCustomerAccessToken('customer@example.com', 'password');

        $saveStateDataServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/carts/mine/state-data",
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $customerToken
            ]
        ];

        $saveStateDataResponse = $this->_webApiCall(
            $saveStateDataServiceInfo,
            ['stateData' => json_encode($this->getValidGiftcardStateData())]
        );
        $this->assertIsInt($saveStateDataResponse);

        $getGiftcardsServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/giftcards/mine",
                'httpMethod' => Request::HTTP_METHOD_GET,
                'token' => $customerToken
            ]
        ];

        $getGiftcardsResponse = $this->_webApiCall($getGiftcardsServiceInfo);
        $this->assertGetRedeemedGiftcardsResponse($getGiftcardsResponse);

        $removeStateDataServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/carts/mine/state-data/{$saveStateDataResponse}",
                'httpMethod' => Request::HTTP_METHOD_DELETE,
                'token' => $customerToken
            ]
        ];

        $removeStateDataResponse = $this->_webApiCall($removeStateDataServiceInfo);
        $this->assertTrue($removeStateDataResponse);
    }

    /**
     * Tests order placement using single giftcard
     *
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_and_shipping_method_saved.php
     */
    public function testSingleGiftcardPayment()
    {
        $cart = $this->getQuoteByReservedOrderId->execute('test_order_1');
        $cartId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());

        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/V1/guest-carts/{$cartId}/payment-information",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $payload = [
            'email' => 'customer@example.com',
            'paymentMethod' => [
                'method' => 'adyen_giftcard',
                'additional_data' => [
                    'stateData' => json_encode($this->getValidGiftcardStateData())
                ]
            ]
        ];

        $saveStateDataResponse = $this->_webApiCall($serviceInfo, $payload);
        $this->assertGreaterThan(0, intval($saveStateDataResponse));
    }

    /**
     * Tests order placement using multiple giftcards
     *
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_and_shipping_method_saved.php
     */
    public function testMultiGiftcardPayment()
    {
        $cart = $this->getQuoteByReservedOrderId->execute('test_order_1');
        $cartId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());

        $numberOfGiftcards = 2;

        for ($i = 0; $i < $numberOfGiftcards; $i++) {
            $saveStateDataServiceInfo = [
                'rest' => [
                    'resourcePath' => "/V1/adyen/guest-carts/{$cartId}/state-data",
                    'httpMethod' => Request::HTTP_METHOD_POST
                ]
            ];

            $this->_webApiCall(
                $saveStateDataServiceInfo,
                ['stateData' => json_encode($this->getValidGiftcardStateData())]
            );
        }

        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/V1/guest-carts/{$cartId}/payment-information",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $payload = [
            'email' => 'customer@example.com',
            'paymentMethod' => [
                'method' => 'adyen_giftcard'
            ]
        ];

        $saveStateDataResponse = $this->_webApiCall($serviceInfo, $payload);
        $this->assertGreaterThan(0, intval($saveStateDataResponse));
    }


    /**
     * Tests order placement using multiple giftcards
     *
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_and_shipping_method_saved.php
     */
    public function testPartialPaymentWithCard()
    {
        $cart = $this->getQuoteByReservedOrderId->execute('test_order_1');
        $cartId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());

        // Redeem one giftcard
        $saveStateDataServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/guest-carts/{$cartId}/state-data",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $this->_webApiCall(
            $saveStateDataServiceInfo,
            ['stateData' => json_encode($this->getValidGiftcardStateData())]
        );

        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/V1/guest-carts/{$cartId}/payment-information",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $payload = [
            'email' => 'customer@example.com',
            'paymentMethod' => [
                'method' => 'adyen_cc',
                'additional_data' => [
                    'stateData' => json_encode($this->getValidCardStateData())
                ]
            ]
        ];

        $placeOrderResponse = $this->_webApiCall($serviceInfo, $payload);
        $this->assertGreaterThan(0, intval($placeOrderResponse));
    }

    /**
     * @return array
     */
    protected function getValidGiftcardStateData(): array
    {
        return [
            'paymentMethod' => [
                'type' => 'giftcard',
                'brand' => 'givex',
                'encryptedCardNumber' => 'test_6036280000000000000',
                'encryptedSecurityCode' => 'test_123'
            ],
            'amount' => [
                'currency' => 'EUR',
                'value' => 5000
            ],
            'giftcard' => [
                'balance' => [
                    'currency' => 'EUR',
                    'value' => 5000
                ],
                'title' => 'Givex'
            ]
        ];
    }

    /**
     * @return array
     */
    protected function getValidCardStateData(): array
    {
        return [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
                'encryptedCardNumber' => 'test_4111111111111111',
                'encryptedExpiryMonth' => 'test_03',
                'encryptedExpiryYear' => 'test_2030',
                'encryptedSecurityCode' => 'test_123'
            ]
        ];
    }

    /**
     * @param string $getGiftcardsResponse
     * @return void
     */
    protected function assertGetRedeemedGiftcardsResponse(string $getGiftcardsResponse): void
    {
        $this->assertJson($getGiftcardsResponse);

        $getGiftcardsResponseDecoded = json_decode($getGiftcardsResponse, true);
        $this->assertArrayHasKey('remainingAmount', $getGiftcardsResponseDecoded);
        $this->assertArrayHasKey('totalDiscount', $getGiftcardsResponseDecoded);
        $this->assertIsString($getGiftcardsResponseDecoded['totalDiscount']);
        $this->assertIsString($getGiftcardsResponseDecoded['remainingAmount']);
        $this->assertArrayHasKey('redeemedGiftcards', $getGiftcardsResponseDecoded);
        $this->assertIsArray($getGiftcardsResponseDecoded['redeemedGiftcards']);
        $this->assertArrayHasKey('stateDataId', $getGiftcardsResponseDecoded['redeemedGiftcards'][0]);
        $this->assertArrayHasKey('brand', $getGiftcardsResponseDecoded['redeemedGiftcards'][0]);
        $this->assertArrayHasKey('title', $getGiftcardsResponseDecoded['redeemedGiftcards'][0]);
        $this->assertArrayHasKey('deductedAmount', $getGiftcardsResponseDecoded['redeemedGiftcards'][0]);
        $this->assertIsString($getGiftcardsResponseDecoded['redeemedGiftcards'][0]['deductedAmount']);
        $this->assertArrayHasKey('balance', $getGiftcardsResponseDecoded['redeemedGiftcards'][0]);
        $this->assertIsArray($getGiftcardsResponseDecoded['redeemedGiftcards'][0]['balance']);
        $this->assertArrayHasKey('currency', $getGiftcardsResponseDecoded['redeemedGiftcards'][0]['balance']);
        $this->assertArrayHasKey('value', $getGiftcardsResponseDecoded['redeemedGiftcards'][0]['balance']);
    }

    /**
     * @param $amount
     * @return array
     */
    protected function getBalanceRequest($amount): array
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
            ],
        ];
    }
}
