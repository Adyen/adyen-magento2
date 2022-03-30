<?php declare(strict_types=1);
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
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

use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class AdyenTest extends GraphQlAbstract
{
    /**
     * @var GetMaskedQuoteIdByReservedOrderId
     */
    private $getMaskedQuoteIdByReservedOrderId;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getMaskedQuoteIdByReservedOrderId = $objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
    }

    /**
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     */
    public function testAdyenPaymentMethods()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
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
    public function testAdyenPaymentStatus()
    {
        $methodCode = "adyen_hpp";
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $stateData = '{\"paymentMethod\":{\"type\":\"ideal\",\"issuer\":\"1154\"}}';
        $adyenAdditionalData = '
        ,
        adyen_additional_data_hpp: {
            brand_code: "ideal",
            stateData: "' . $stateData . '"
        }';
        $query = $this->getPlaceOrderQuery($maskedQuoteId, $methodCode, $adyenAdditionalData);

        $response = $this->graphQlMutation($query);

        self::assertArrayHasKey('placeOrder', $response);
        self::assertArrayHasKey('order', $response['placeOrder']);
        self::assertArrayHasKey('order_number', $response['placeOrder']['order']);
        self::assertArrayHasKey('cart_id', $response['placeOrder']['order']);
        self::assertArrayHasKey('adyen_payment_status', $response['placeOrder']['order']);
        self::assertArrayHasKey('isFinal', $response['placeOrder']['order']['adyen_payment_status']);
        self::assertArrayHasKey('resultCode', $response['placeOrder']['order']['adyen_payment_status']);
        self::assertArrayHasKey('action', $response['placeOrder']['order']['adyen_payment_status']);
    }

    public function testAdyenPaymentDetails()
    {
        $query = <<<QUERY
{
  adyenPaymentDetails(payload: "{\"orderId\": \"nothing here\"}", cart_id: "not found") {
    isFinal
  }
}
QUERY;
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('Could not find a cart with ID "not found"');

        $this->graphQlQuery($query);
    }

    /**
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     */
    public function testSetAdyenPaymentMethodOnCart()
    {
        $methodCode = "adyen_hpp";
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');
        $stateData = '{\"paymentMethod\":{\"type\":\"ideal\",\"issuer\":\"1154\"}}';
        $adyenAdditionalData = '
        ,
        adyen_additional_data_hpp: {
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
}
