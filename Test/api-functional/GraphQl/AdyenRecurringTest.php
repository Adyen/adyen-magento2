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

use Magento\Framework\Exception\AuthenticationException;
use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class AdyenRecurringTest extends GraphQlAbstract
{
    private GetMaskedQuoteIdByReservedOrderId $getMaskedQuoteIdByReservedOrderId;
    private CustomerTokenServiceInterface $customerTokenService;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getMaskedQuoteIdByReservedOrderId = $objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
    }

    /**
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/customer/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_billing_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_flatrate_shipping_method.php
     */
    public function testGenerateCardTokenAndVaultPayment()
    {
        // Place initial order with storePaymentMethod to generate vault token
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $stateData = <<<JSON
            {
                "paymentMethod": {
                    "type": "scheme",
                    "holderName": "Foo Bar",
                    "encryptedCardNumber": "test_4111111111111111",
                    "encryptedExpiryMonth": "test_03",
                    "encryptedExpiryYear": "test_2030",
                    "encryptedSecurityCode": "test_737"
                },
                "storePaymentMethod": true,
                "browserInfo": {
                    "acceptHeader": "*/*",
                    "colorDepth": 24,
                    "language": "en-US",
                    "javaEnabled": false,
                    "screenHeight": 1080,
                    "screenWidth": 1920,
                    "userAgent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.41 Safari/537.36",
                    "timeZoneOffset": -120
                },
                "origin": "http://localhost",
                "clientStateDataIndicator": true
            }
        JSON;
        $adyenAdditionalData = '
        adyen_additional_data_cc: {
            cc_type: "VI",
            stateData: ' . json_encode($stateData) . '
        }';
        $query = $this->getPlaceOrderQuery($maskedQuoteId, "adyen_cc", $adyenAdditionalData);
        $response = $this->graphQlMutation($query, [], '', $this->getHeaderMap());

        self::assertEquals(
            'Authorised',
            $response['placeOrder']['order']['adyen_payment_status']['resultCode']
        );

        // Query for the stored vault tokens
        $customerTokensQuery = <<<QUERY
            query {
              customerPaymentTokens {
                items {
                  details
                  public_hash
                  payment_method_code
                  type
                }
              }
            }
        QUERY;

        $tokensResponse = $this->graphQlQuery($customerTokensQuery, [], '', $this->getHeaderMap());

        $customerTokens = $tokensResponse['customerPaymentTokens']['items'];
        self::assertNotEmpty($customerTokens, 'Expected at least one stored payment token');

        $vaultToken = reset($customerTokens);
        self::assertArrayHasKey('public_hash', $vaultToken);
        self::assertEquals('adyen_cc', $vaultToken['payment_method_code']);

        // Prepare a new quote, set billing and shipping address and return the masked quote id
        $newMaskedQuoteId = $this->prepareNewQuote();

        // Place order using the vault payment token
        $vaultAdyenAdditionalData = "
        adyen_additional_data_cc: {
            public_hash: \"{$vaultToken['public_hash']}\"
        }";

        $vaultPaymentQuery = $this->getPlaceOrderQuery(
            $newMaskedQuoteId,
            "adyen_cc_vault",
            $vaultAdyenAdditionalData
        );
        $vaultResponse = $this->graphQlMutation($vaultPaymentQuery, [], '', $this->getHeaderMap());

        self::assertEquals(
            'Authorised',
            $vaultResponse['placeOrder']['order']['adyen_payment_status']['resultCode']
        );
    }

    /**
     * @param string $maskedQuoteId
     * @param string $methodCode
     * @param string $adyenAdditionalData
     * @return string
     */
    private function getPlaceOrderQuery(
        string $maskedQuoteId,
        string $methodCode,
        string $adyenAdditionalData
    ): string {
        return <<<QUERY
            mutation {
                setPaymentMethodOnCart(
                    input: {
                        cart_id: "$maskedQuoteId"
                        payment_method: {
                          code: "$methodCode",
                          {$adyenAdditionalData}
                        }
                    }
                ) {
                    cart {
                        selected_payment_method {
                            code
                            title
                        }
                    }
                }

                placeOrder(
                    input: {
                        cart_id: "$maskedQuoteId"
                    }
                ) {
                    order {
                        order_number
                        cart_id
                        adyen_payment_status {
                            isFinal
                            resultCode
                            additionalData
                            action
                        }
                    },
                    errors {
                        message,
                        code
                    }
                }
            }
        QUERY;
    }

    /**
     * @return mixed
     * @throws AuthenticationException
     */
    private function prepareNewQuote(): mixed
    {
        // Create a new cart
        $createCartMutation = <<<QUERY
            mutation {
                createEmptyCart
            }
        QUERY;

        $createCartResponse = $this->graphQlMutation($createCartMutation, [], '', $this->getHeaderMap());
        $newMaskedQuoteId = $createCartResponse['createEmptyCart'];

        // Add product to the new cart
        $addProductMutation = <<<QUERY
            mutation {
                addSimpleProductsToCart(
                    input: {
                        cart_id: "$newMaskedQuoteId"
                        cart_items: [
                            {
                                data: {
                                    quantity: 1
                                    sku: "simple_product"
                                }
                            }
                        ]
                    }
                ) {
                    cart {
                        items {
                            quantity
                            product {
                                sku
                            }
                        }
                    }
                }
            }
        QUERY;

        $this->graphQlMutation($addProductMutation, [], '', $this->getHeaderMap());

        // Set shipping address
        $setShippingAddressMutation = <<<QUERY
            mutation {
                setShippingAddressesOnCart(
                    input: {
                        cart_id: "$newMaskedQuoteId"
                        shipping_addresses: [
                            {
                                address: {
                                    firstname: "John"
                                    lastname: "Smith"
                                    company: "Test company"
                                    street: ["test street 1", "test street 2"]
                                    city: "Texas City"
                                    postcode: "78717"
                                    telephone: "5765432100"
                                    region: "TX"
                                    country_code: "US"
                                    save_in_address_book: false
                                }
                            }
                        ]
                    }
                ) {
                    cart {
                        shipping_addresses {
                            firstname
                            lastname
                        }
                    }
                }
            }
        QUERY;

        $this->graphQlMutation($setShippingAddressMutation, [], '', $this->getHeaderMap());

        // Set billing address
        $setBillingAddressMutation = <<<QUERY
            mutation {
                setBillingAddressOnCart(
                    input: {
                        cart_id: "$newMaskedQuoteId"
                        billing_address: {
                            address: {
                                firstname: "John"
                                lastname: "Smith"
                                company: "Test company"
                                street: ["test street 1", "test street 2"]
                                city: "Texas City"
                                postcode: "78717"
                                telephone: "5765432100"
                                region: "TX"
                                country_code: "US"
                                save_in_address_book: false
                            }
                        }
                    }
                ) {
                    cart {
                        billing_address {
                            firstname
                            lastname
                        }
                    }
                }
            }
        QUERY;

        $this->graphQlMutation($setBillingAddressMutation, [], '', $this->getHeaderMap());

        // Set shipping method
        $setShippingMethodMutation = <<<QUERY
            mutation {
                setShippingMethodsOnCart(
                    input: {
                        cart_id: "$newMaskedQuoteId"
                        shipping_methods: [
                            {
                                carrier_code: "flatrate"
                                method_code: "flatrate"
                            }
                        ]
                    }
                ) {
                    cart {
                        shipping_addresses {
                            selected_shipping_method {
                                carrier_code
                                method_code
                            }
                        }
                    }
                }
            }
        QUERY;

        $this->graphQlMutation($setShippingMethodMutation, [], '', $this->getHeaderMap());

        return $newMaskedQuoteId;
    }

    /**
     * Create a header with customer token
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws AuthenticationException
     * @see \Magento\GraphQl\Quote\Customer\GetCustomerCartTest::getHeaderMap
     */
    private function getHeaderMap(string $username = 'customer@example.com', string $password = 'password'): array
    {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        return ['Authorization' => 'Bearer ' . $customerToken];
    }
}
