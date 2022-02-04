<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Adyen\Payment\GraphQl;

use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;

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

        $query =
            <<<QUERY
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

        $response = $this->graphQlQuery(
            $query,
            [],
            '',
        );

        var_dump($response);
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

        self::assertArrayHasKey('setPaymentMethodAndPlaceOrder', $response);
        self::assertArrayHasKey('order', $response['setPaymentMethodAndPlaceOrder']);
        self::assertArrayHasKey('order_number', $response['setPaymentMethodAndPlaceOrder']['order']);
        self::assertArrayHasKey('adyen_payment_status', $response['setPaymentMethodAndPlaceOrder']['order']);
        self::assertArrayHasKey('isFinal', $response['setPaymentMethodAndPlaceOrder']['order']['adyen_payment_status']);
        self::assertArrayHasKey('resultCode', $response['setPaymentMethodAndPlaceOrder']['order']['adyen_payment_status']);
        self::assertArrayHasKey('action', $response['setPaymentMethodAndPlaceOrder']['order']['adyen_payment_status']);
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
    public function disabledTstAdyenPaymentDetails()
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

        $resultRedirect = ''; /* ResultRedirect cannot be retrieved */

        $payloadArray['order_id'] = $response['setPaymentMethodAndPlaceOrder']['order']['order_number'];
        $payloadArray['redirectResult'] = $resultRedirect;

        $payload = str_replace('"', '/"', json_encode($payloadArray));

        $query =
            <<<QUERY
{
  adyenPaymentDetails(payload: "$payload") {
    isFinal,
    resultCode,
    additionalData,
    action
  }
}
QUERY;

        $response = $this->graphQlQuery(
            $query,
            [],
            '',
        );


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
     * @return string
     */
    private function getQuery(
        string $maskedQuoteId,
        string $methodCode,
        string $adyenAdditionalData
    ): string
    {
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
     * @return string
     */
    private function getPlaceOrderQuery(
        string $maskedQuoteId,
        string $methodCode,
        string $adyenAdditionalData
    ): string
    {
        return <<<QUERY
mutation {
  setPaymentMethodAndPlaceOrder(input: {
      cart_id: "$maskedQuoteId"
      payment_method: {
          code: "$methodCode",
          {$adyenAdditionalData}
      }
  }) {
    order {
      order_number,
      adyen_payment_status {
        isFinal,
        resultCode,
        additionalData,
        action
        }
    }
  }
}
QUERY;
    }


}
