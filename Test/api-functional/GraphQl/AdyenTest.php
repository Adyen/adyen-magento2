<?php declare(strict_types=1);
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
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
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdyenTest extends GraphQlAbstract
{
    private $getMaskedQuoteIdByReservedOrderId;
    private $customerTokenService;
    private $productRepository;
    private $productFactory;
    private $quoteFactory;
    private $cartRepository;
    private $storeManager;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getMaskedQuoteIdByReservedOrderId = $objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->productFactory = $objectManager->get(ProductFactory::class);
        $this->quoteFactory = $objectManager->get(QuoteFactory::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
    }

    private function createSimpleProduct()
    {
        $product = $this->productFactory->create();
        $product->setTypeId('simple')
            ->setAttributeSetId(4)
            ->setName('Simple Product for Adyen Test')
            ->setSku('simple-product-adyen-test')
            ->setPrice(10)
            ->setVisibility(4)
            ->setStatus(1)
            ->setWebsiteIds([$this->storeManager->getStore()->getWebsiteId()])
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);

        return $this->productRepository->save($product);
    }

    public function testAdyenPaymentMethods()
    {
        $product = $this->createSimpleProduct();

        $quote = $this->quoteFactory->create();
        $quote->addProduct($product, 1);
        $quote->setReservedOrderId('test_order_adyen');
        $this->cartRepository->save($quote);

        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_adyen');

        $query = <<<QUERY
{
  adyenPaymentMethods(cart_id: "$maskedQuoteId") {
    paymentMethodsResponse {
        paymentMethods {
           name,
           type
        }
    },
    paymentMethodsExtraDetails {
        icon {
            url,
            width
            height
        }
    }
  }
}
QUERY;


        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('adyenPaymentMethods', $response);
        $this->assertArrayHasKey('paymentMethodsResponse', $response['adyenPaymentMethods']);
        $this->assertArrayHasKey('paymentMethodsExtraDetails', $response['adyenPaymentMethods']);

        // Clean up
        $this->productRepository->delete($product);
    }

    /**
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/set_guest_email.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_billing_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_flatrate_shipping_method.php
     */
    public function testAdyenPaymentStatus(): void
    {
        $methodCode = "adyen_ideal";
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $stateData = '{\"paymentMethod\":{\"type\":\"ideal\"}}';
        $adyenAdditionalData = '
        ,
        adyen_additional_data: {
            brand_code: "ideal",
            stateData: "' . $stateData . '",
            returnUrl: "http://localhost/checkout/?id=:merchantReference&done=1"
        }';
        $query = $this->getPlaceOrderQuery($maskedQuoteId, $methodCode, $adyenAdditionalData);

        $response = $this->graphQlMutation($query);

        self::assertArrayHasKey('placeOrder', $response, 'placeOrder key is missing in the response');
        if (isset($response['placeOrder']['order'])) {
            $order = $response['placeOrder']['order'];
            self::assertArrayHasKey('order_number', $order, 'order_number is missing');
            self::assertArrayHasKey('cart_id', $order, 'cart_id is missing');
            if (isset($order['adyen_payment_status'])) {
                $paymentStatus = $order['adyen_payment_status'];
                self::assertArrayHasKey('isFinal', $paymentStatus, 'isFinal is missing in adyen_payment_status');
                self::assertArrayHasKey('resultCode', $paymentStatus, 'resultCode is missing in adyen_payment_status');
                self::assertArrayHasKey('action', $paymentStatus, 'action is missing in adyen_payment_status');
            } else {
                $this->fail('adyen_payment_status is missing in the order');
            }
        } else {
            $this->fail('order is missing in the placeOrder response');
        }
    }

    public function testAdyenPaymentDetails()
    {
        $query = <<<QUERY
mutation {
  adyenPaymentDetails(payload: "{\"orderId\": \"nothing here\"}", cart_id: "not found") {
    isFinal
  }
}
QUERY;
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('Could not find a cart with ID "not found"');

        $this->graphQlMutation($query);
    }

    /**
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     */
    public function testSetAdyenPaymentMethodOnCart(): void
    {
        $methodCode = "adyen_ideal";
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $stateData = '{\"paymentMethod\":{\"type\":\"ideal\",\"issuer\":\"1154\"}}';
        $adyenAdditionalData = '
        ,
        adyen_additional_data: {
            brand_code: "ideal",
            stateData: "' . $stateData . '"
        }';
        $query = $this->getQuery($maskedQuoteId, $methodCode, $adyenAdditionalData);

        $response = $this->graphQlMutation($query);

        self::assertArrayHasKey('setPaymentMethodOnCart', $response);
        self::assertArrayHasKey('cart', $response['setPaymentMethodOnCart']);
        self::assertArrayHasKey('selected_payment_method', $response['setPaymentMethodOnCart']['cart']);
        self::assertEquals($methodCode, $response['setPaymentMethodOnCart']['cart']['selected_payment_method']['code']);
    }

    /**
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/set_guest_email.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_billing_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_flatrate_shipping_method.php
     */
    public function testCreditCardGuest()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $stateData = <<<'JSON'
{
    "paymentMethod": {
        "type": "scheme",
        "holderName": "Foo Bar",
        "encryptedCardNumber": "test_4111111111111111",
        "encryptedExpiryMonth": "test_03",
        "encryptedExpiryYear": "test_2030",
        "encryptedSecurityCode": "test_737"
    },
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

        $response = $this->graphQlMutation($query);

        self::assertArrayHasKey('placeOrder', $response, 'placeOrder key is missing in the response');
        if (isset($response['placeOrder']['order'])) {
            $order = $response['placeOrder']['order'];
            self::assertArrayHasKey('order_number', $order, 'order_number is missing');
            self::assertArrayHasKey('cart_id', $order, 'cart_id is missing');
            if (isset($order['adyen_payment_status'])) {
                $paymentStatus = $order['adyen_payment_status'];
                self::assertArrayHasKey('isFinal', $paymentStatus, 'isFinal is missing in adyen_payment_status');
                self::assertArrayHasKey('resultCode', $paymentStatus, 'resultCode is missing in adyen_payment_status');
                self::assertTrue($paymentStatus['isFinal'], 'Payment is not final');
                self::assertEquals('Authorised', $paymentStatus['resultCode'], 'Unexpected result code');
            } else {
                $this->fail('adyen_payment_status is missing in the order');
            }
        } else {
            $this->fail('order is missing in the placeOrder response');
        }
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
    public function testCreditCardCustomer()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $stateData = <<<'JSON'
{
    "paymentMethod": {
        "type": "scheme",
        "holderName": "Foo Bar",
        "encryptedCardNumber": "test_4111111111111111",
        "encryptedExpiryMonth": "test_03",
        "encryptedExpiryYear": "test_2030",
        "encryptedSecurityCode": "test_737"
    },
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

        self::assertArrayHasKey('placeOrder', $response, 'placeOrder key is missing in the response');
        if (isset($response['placeOrder']['order']['adyen_payment_status'])) {
            self::assertEquals('Authorised', $response['placeOrder']['order']['adyen_payment_status']['resultCode'], 'Unexpected result code');
        } else {
            $this->fail('adyen_payment_status is missing in the order');
        }
    }

    /**
     * @param string $maskedQuoteId
     * @param string $methodCode
     * @param string $adyenAdditionalData
     * @return string
     */
    private function getQuery(
        string $maskedQuoteId,
        string $methodCode,
        string $adyenAdditionalData
    ): string {
        return <<<QUERY
mutation {
  setPaymentMethodOnCart(input: {
    cart_id: "{$maskedQuoteId}",
    payment_method: {
      code: "{$methodCode}"
      {$adyenAdditionalData}
    }
  }) {
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
QUERY;
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
        }
    }
}
QUERY;
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
        try {
            $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
            return ['Authorization' => 'Bearer ' . $customerToken];
        } catch (AuthenticationException $e) {
            $this->fail('Failed to create customer token: ' . $e->getMessage());
        }
    }
}
